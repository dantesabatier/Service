<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Throwable;
use function Sabatier\Foundation\human_readable_value;

/**
 * Drives the agentic loop for a single run.
 *
 * Repeatedly calls the LLM client, executes any tool calls via the ToolRegistry, and feeds
 * results back into the conversation until the model explicitly completes the turn.
 * A response cut off by its output limit or refused by the model ends the run as incomplete
 * rather than being laundered into a conclusion merely because it contains no tool calls.
 * Returns an `LLMRun` containing only the messages generated during
 * this run (not the input history) and the total tokens consumed.
 *
 * The loop also stops when it runs out of iterations, runs past its time limit, exhausts a shared execution-policy budget, needs approval for a write, or the provider fails after the client has exhausted its retries. All are reported as incomplete (`LLMRun::$isComplete`) and told apart by `LLMRun::$stopReason`; a caller that treats them alike presents unfinished work as a conclusion. A provider failure ends the run rather than propagating, so the messages and tokens already paid for survive it, and `LLMRun::$isRetryable` carries whether another attempt is worth making — a rate limit clears on its own, a rejected key does not.
 *
 * Tool failures are resolved by the registry, not here. A correctable mistake — the model
 * mis-called a tool — comes back as a failed `ToolResult` and is fed to the model as an
 * error-flagged tool message so the loop can self-correct; it is not cached, since the same
 * call may succeed once corrected. A real program fault propagates out of the registry and
 * aborts the run for the caller to handle.
 *
 * Successful calls to a read-only tool are cached for the length of the run, keyed on the tool name and arguments regardless of the order the model emitted them in, so a model that loops on one call is told what it already got back instead of paying for the call again. A tool that writes is never cached: two identical calls are two mutations the model asked for, and answering the second from the cache would silently drop one.
 */
final class LLMAgent
{
    private const string subagentToolName = "run_subagent";
    private const string subagentNoAnswer = "The subagent produced no answer.";
    private const string subagentTaskRequired = "Subagent task is required.";
    private const string subagentIncomplete = "The subagent ran out of iterations before finishing. Narrow the task and try again.";
    private const string subagentProviderFailure = "The subagent could not reach the model provider. Retrying may work.";
    private const string subagentProviderRefused = "The subagent could not reach the model provider, and the failure will repeat: it needs the server's configuration fixed, not another attempt. Report it rather than retrying.";
    private const string subagentDeadline = "The subagent ran out of time before finishing. Narrow the task and try again.";
    private const string subagentOutputLimit = "The subagent ran out of output tokens before finishing. Narrow the task and try again.";
    private const string subagentRefusal = "The subagent refused the task. Do not present its partial output as an answer.";
    private const string writeApprovalRequired = "This tool changes state and requires explicit user approval before it can run.";
    private const string toolCallLimit = "The shared tool-call budget is exhausted.";
    private const string subagentCallLimit = "The shared subagent budget is exhausted.";
    private const string inputTokenLimit = "The shared input-token budget is exhausted.";
    private const string outputTokenLimit = "The shared output-token budget is exhausted.";
    private const string totalTokenLimit = "The shared total-token budget is exhausted.";
    private const string contextLimit = "The assembled context exceeds its configured limit without a safe turn left to remove.";
    private const int subagentMaxIterations = 8;
    /** @var ToolDescriptor Descriptor for the synthetic delegation tool offered only by parent agents. */
    private ToolDescriptor $subagentToolDescriptor {
        get => $this->subagentToolDescriptor ??= new ToolDescriptor(
            self::subagentToolName,
            "Launch a focused subagent with the same tool catalogue to complete one bounded task. The subagent cannot launch further subagents.",
            [
                "type" => "object",
                "properties" => [
                    "task" => ["type" => "string", "description" => "The focused task the subagent should complete."],
                    "context" => ["type" => "string", "description" => "Optional context the subagent needs to complete the task."],
                ],
                "required" => ["task"],
                "additionalProperties" => false,
            ],
            "Run Subagent"
        );
    }
    /** @var ArrayClass<ToolDescriptor> */
    private ArrayClass $toolList {
        get {
            if (isset($this->toolList)) {
                return $this->toolList;
            }
            if ($this->canSpawnSubagents) {
                return $this->toolList = $this->toolRegistry->list->appending($this->subagentToolDescriptor);
            }
            return $this->toolList = $this->toolRegistry->list;
        }
    }
    /** @var float|null When the current run must stop, or `null` when it is unbounded. Set at the top of `run()` so a subagent can inherit what is left of it rather than a fresh copy of the whole limit. */
    private ?float $deadline = null;
    private readonly LLMExecutionPolicy $executionPolicy;
    private readonly LLMContextAssembler $contextAssembler;

    /**
     * @param LLMClient $client The provider client every turn is sent through.
     * @param ToolRegistry $toolRegistry The catalogue the model may call, and the funnel every call goes through.
     * @param int $maxIterations Turns the loop may take before it gives up on the model concluding.
     * @param bool $canSpawnSubagents Whether the synthetic subagent tool is offered alongside the registry's own.
     * @param float|null $timeLimit Seconds the whole run may take, or `null` for no limit. Bounds the wall clock the iteration cap cannot: a turn that waits out a provider's backoff costs time without costing an iteration.
     * @param LLMExecutionPolicy|null $executionPolicy Hard limits and write approvals shared with every subagent. Null uses the secure default policy.
     * @param LLMContextAssembler|null $contextAssembler Formats and bounds the history sent on every model turn. Null preserves the complete history.
     */
    public function __construct(private readonly LLMClient $client, private readonly ToolRegistry $toolRegistry, private readonly int $maxIterations = 25, private readonly bool $canSpawnSubagents = true, private readonly ?float $timeLimit = null, ?LLMExecutionPolicy $executionPolicy = null, ?LLMContextAssembler $contextAssembler = null)
    {
        $this->executionPolicy = $executionPolicy ?? new LLMExecutionPolicy();
        $this->contextAssembler = $contextAssembler ?? new WindowedLLMContextAssembler();
    }

    /**
     * Runs the agentic loop and returns only the new messages generated (not the input).
     *
     * @param ArrayClass<LLMMessage> $messages The input conversation history; cloned, never mutated.
     * @param string|null $systemPrompt The system prompt to send on every turn, or null for none.
     * @return LLMRun The messages generated during this run, the total input and output tokens consumed, and why the loop stopped.
     * @throws Throwable A fatal program fault raised by a tool, left to propagate out of the run.
     */
    public function run(ArrayClass $messages, ?string $systemPrompt = null): LLMRun
    {
        return $this->runWithState($messages, $systemPrompt, new LLMExecutionState());
    }

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @throws Throwable
     */
    private function runWithState(ArrayClass $messages, ?string $systemPrompt, LLMExecutionState $executionState): LLMRun
    {
        $history = clone $messages;
        /** @var ArrayClass<LLMMessage> $newMessages */
        $newMessages = new ArrayClass();
        /** @var int<0, max> $totalInputTokens */
        $totalInputTokens = 0;
        /** @var int<0, max> $totalOutputTokens */
        $totalOutputTokens = 0;
        /** @var Dictionary<string> $toolCallCache */
        $toolCallCache = new Dictionary();
        $iterations = 0;
        $stopReason = LLMRunStopReason::iterationCap;
        $isRetryable = true;
        $deadline = $this->deadline = $this->timeLimit === null ? null : microtime(true) + $this->timeLimit;
        while ($iterations++ < $this->maxIterations) {
            if (($tokenStopReason = $executionState->tokenStopReason($this->executionPolicy, true)) !== null) {
                $stopReason = $tokenStopReason;
                break;
            }
            if ($deadline !== null && microtime(true) >= $deadline) {
                $stopReason = LLMRunStopReason::deadline;
                break;
            }
            $context = $this->contextAssembler->assemble($history, $this->toolList, $systemPrompt);
            if (!$context->isWithinLimit) {
                $stopReason = LLMRunStopReason::contextLimit;
                $isRetryable = false;
                break;
            }
            try {
                $turn = $this->client->complete($context->messages, $this->toolList, $context->systemPrompt);
            } catch (LLMProviderException $exception) {
                $stopReason = LLMRunStopReason::providerFailure;
                $isRetryable = $exception->isTransient;
                break;
            }
            $totalInputTokens += $turn->inputTokens;
            $totalOutputTokens += $turn->outputTokens;
            $executionState->recordTokens($turn->inputTokens, $turn->outputTokens);
            $assistantMsg = new LLMMessage(LLMMessageRole::assistant, $turn->text, $turn->toolCalls, outputTokens: $turn->outputTokens, thinkingBlocks: $turn->thinkingBlocks, reasoningContent: $turn->reasoningContent);
            $history->append($assistantMsg);
            $newMessages->append($assistantMsg);
            if (($tokenStopReason = $executionState->tokenStopReason($this->executionPolicy, false)) !== null) {
                $stopReason = $tokenStopReason;
                break;
            }
            $ending = match ($turn->stopReason) {
                LLMTurnStopReason::toolUse => null,
                LLMTurnStopReason::completed => [LLMRunStopReason::done, null],
                LLMTurnStopReason::outputLimit => [LLMRunStopReason::outputLimit, true],
                LLMTurnStopReason::refusal => [LLMRunStopReason::refusal, false],
            };
            if ($ending !== null) {
                [$stopReason, $isRetryable] = $ending;
                break;
            }
            if (($tokenStopReason = $executionState->tokenStopReason($this->executionPolicy, true)) !== null) {
                $stopReason = $tokenStopReason;
                break;
            }
            foreach ($turn->toolCalls as $toolCall) {
                $isSubagent = $this->canSpawnSubagents && $toolCall->name === self::subagentToolName;
                if (!$isSubagent && $this->toolRegistry->isRegistered($toolCall->name) && !$this->toolRegistry->isReadOnlyCall($toolCall->name, $toolCall->arguments) && !$this->executionPolicy->approvesWrite($toolCall)) {
                    $toolMsg = new LLMMessage(LLMMessageRole::tool, self::writeApprovalRequired, toolCallId: $toolCall->id, isError: true);
                    $history->append($toolMsg);
                    $newMessages->append($toolMsg);
                    $stopReason = LLMRunStopReason::writeApprovalRequired;
                    $isRetryable = false;
                    break 2;
                }
                if (($callStopReason = $executionState->consumeToolCall($this->executionPolicy, $isSubagent)) !== null) {
                    $toolMsg = new LLMMessage(LLMMessageRole::tool, $this->stopReasonText($callStopReason), toolCallId: $toolCall->id, isError: true);
                    $history->append($toolMsg);
                    $newMessages->append($toolMsg);
                    $stopReason = $callStopReason;
                    break 2;
                }
                $cacheKey = $this->toolCallCacheKey($toolCall);
                $isError = false;
                $propagatedStopReason = null;
                $propagatedRetryable = null;
                $cached = $toolCallCache[$cacheKey];
                if ($cached !== null) {
                    $text = $this->repeatedCallText($cached);
                } else {
                    if ($isSubagent) {
                        $subRun = $this->runSubagent($toolCall->arguments, $systemPrompt, $executionState);
                        $totalInputTokens += $subRun->inputTokens;
                        $totalOutputTokens += $subRun->outputTokens;
                        $answer = $this->subagentResultText($subRun);
                        $text = $answer ?? $this->subagentFailureText($subRun);
                        $isError = $answer === null;
                        $propagatedStopReason = $this->propagatedStopReason($subRun->stopReason);
                        $propagatedRetryable = $subRun->isRetryable;
                    } else {
                        $result = $this->toolRegistry->call($toolCall->name, $toolCall->arguments);
                        $text = $result->text;
                        $isError = $result->isError;
                    }
                    if (!$isError && $this->isCacheable($toolCall->name)) {
                        $toolCallCache[$cacheKey] = $text;
                    }
                }
                $toolMsg = new LLMMessage(LLMMessageRole::tool, $text, toolCallId: $toolCall->id, isError: $isError);
                $history->append($toolMsg);
                $newMessages->append($toolMsg);
                if ($propagatedStopReason !== null) {
                    $stopReason = $propagatedStopReason;
                    $isRetryable = $propagatedRetryable;
                    break 2;
                }
                if (($tokenStopReason = $executionState->tokenStopReason($this->executionPolicy, true)) !== null) {
                    $stopReason = $tokenStopReason;
                    break 2;
                }
            }
        }
        return new LLMRun($newMessages, $totalInputTokens, $totalOutputTokens, $stopReason, $isRetryable);
    }

    /**
     * The seconds a nested run may take, or `null` when this one is unbounded.
     *
     * A subagent inherits what is left of the parent's window rather than a fresh copy of the limit: given the limit itself, a subagent started near the end would run for as long again, and the bound the caller asked for would mean nothing. A window already spent yields zero, which stops the sub-run on its first check instead of letting it take one more turn.
     */
    private function remainingTime(): ?float
    {
        return $this->deadline === null ? null : max(0.0, $this->deadline - microtime(true));
    }

    /**
     * Whether a repeated call to this tool may be answered from the run's cache instead of executed again.
     *
     * Only a tool that declares itself read-only qualifies. A tool that writes must run every time it is called: two identical `create` calls are two rows the model asked for, and serving the second from the cache silently drops the write. The synthetic subagent tool is never cacheable either — it runs a whole nested conversation, and the same task posed twice is not a repeated read.
     */
    private function isCacheable(string $name): bool
    {
        return $name !== self::subagentToolName && $this->toolRegistry->isCacheable($name);
    }

    /**
     * Identifies a tool call by name and arguments, independently of the order the model
     * happened to emit those arguments in.
     *
     * `Dictionary::$description` serializes in insertion order, so keying on it directly gives
     * `{a: 1, b: 2}` and `{b: 2, a: 1}` different keys for what is the same call. Sorting the
     * keys first makes the identity depend on the arguments themselves rather than on their
     * order. Only the top level is sorted; a nested dictionary that differs solely in ordering
     * still misses, which costs a cache hit and never a wrong one.
     */
    private function toolCallCacheKey(LLMToolCall $toolCall): string
    {
        $arguments = $toolCall->arguments;
        $canonical = $arguments->keys->sort()->map(fn(string $key): string => sprintf("%s: %s", $key, human_readable_value($arguments[$key])))->join(", ");
        return md5("$toolCall->name($canonical)");
    }

    /**
     * Tells the model it has already made this exact call, and what it returned.
     *
     * The instruction comes first: a long cached result would otherwise push it past the point
     * where the model is still reading closely, which is precisely the case where the repeated
     * call is most expensive.
     */
    private function repeatedCallText(string $cached): string
    {
        return "Do not call this tool with these arguments again — you already did, and it returned:\n\n$cached";
    }

    /**
     * @param Dictionary<mixed> $arguments
     * @throws Throwable
     */
    private function runSubagent(Dictionary $arguments, ?string $parentSystemPrompt, LLMExecutionState $executionState): LLMRun
    {
        $task = trim((string)$arguments["task"]);
        if ($task === "") {
            return new LLMRun(new ArrayClass());
        }
        $context = trim((string)$arguments["context"]);
        $content = $context === "" ? $task : "Task:\n$task\n\nContext:\n$context";
        $subagent = new self($this->client, $this->toolRegistry, self::subagentMaxIterations, false, $this->remainingTime(), $this->executionPolicy, $this->contextAssembler);
        return $subagent->runWithState(new ArrayClass([new LLMMessage(LLMMessageRole::user, $content)]), $parentSystemPrompt, $executionState);
    }

    /**
     * Returns the subagent's final answer, or `null` when the sub-run produced
     * no assistant content to report back.
     *
     * A sub-run stopped by the iteration cap never reaches a concluding turn, so the last
     * assistant text it produced is a step in the middle of the work, not an answer. Reporting
     * it as one would launder an unfinished run into a confident result for the parent, so the
     * cap is surfaced as a failure instead.
     */
    private function subagentResultText(LLMRun $run): ?string
    {
        if (!$run->isComplete) {
            return null;
        }
        return $run->messages->last(fn(LLMMessage $message): bool => $message->role === LLMMessageRole::assistant && $message->content !== null && $message->content !== "")?->content;
    }

    /**
     * Explains why a sub-run yielded no answer, in terms the model can act on.
     *
     * The failures are distinct and call for different corrections: a run that never started because the call omitted `task`, one that finished with nothing to say, one the iteration cap cut short, one that ran out of time, and one the provider broke off. Collapsing them would tell the model to retry the case it should reformulate, and reformulate the case it should retry.
     *
     * A provider failure splits again on `LLMRun::$isRetryable`, because the two halves need opposite advice: a rate limit clears on its own and is worth another attempt, while a rejected key answers the same way every time and only burns the parent's iterations. Only a definite `false` is treated as permanent — an unknown stays retryable, which costs one wasted attempt rather than abandoning work that would have succeeded.
     *
     * The missing `task` is the one case the stop reason cannot express — the run never began, so it stopped for no reason at all — and an empty message list is what identifies it.
     */
    private function subagentFailureText(LLMRun $run): string
    {
        if ($run->messages->isEmpty && $run->stopReason === LLMRunStopReason::done) {
            return self::subagentTaskRequired;
        }
        return match ($run->stopReason) {
            LLMRunStopReason::providerFailure => $run->isRetryable === false ? self::subagentProviderRefused : self::subagentProviderFailure,
            LLMRunStopReason::iterationCap => self::subagentIncomplete,
            LLMRunStopReason::deadline => self::subagentDeadline,
            LLMRunStopReason::outputLimit => self::subagentOutputLimit,
            LLMRunStopReason::refusal => self::subagentRefusal,
            LLMRunStopReason::toolCallLimit => self::toolCallLimit,
            LLMRunStopReason::subagentCallLimit => self::subagentCallLimit,
            LLMRunStopReason::inputTokenLimit => self::inputTokenLimit,
            LLMRunStopReason::outputTokenLimit => self::outputTokenLimit,
            LLMRunStopReason::totalTokenLimit => self::totalTokenLimit,
            LLMRunStopReason::writeApprovalRequired => self::writeApprovalRequired,
            LLMRunStopReason::contextLimit => self::contextLimit,
            LLMRunStopReason::done => self::subagentNoAnswer,
        };
    }

    private function stopReasonText(LLMRunStopReason $stopReason): string
    {
        return match ($stopReason) {
            LLMRunStopReason::toolCallLimit => self::toolCallLimit,
            LLMRunStopReason::subagentCallLimit => self::subagentCallLimit,
            LLMRunStopReason::inputTokenLimit => self::inputTokenLimit,
            LLMRunStopReason::outputTokenLimit => self::outputTokenLimit,
            LLMRunStopReason::totalTokenLimit => self::totalTokenLimit,
            LLMRunStopReason::done, LLMRunStopReason::iterationCap, LLMRunStopReason::deadline, LLMRunStopReason::providerFailure, LLMRunStopReason::outputLimit, LLMRunStopReason::refusal, LLMRunStopReason::writeApprovalRequired, LLMRunStopReason::contextLimit => "The agent run stopped before this tool could execute.",
        };
    }

    private function propagatedStopReason(LLMRunStopReason $stopReason): ?LLMRunStopReason
    {
        return match ($stopReason) {
            LLMRunStopReason::toolCallLimit, LLMRunStopReason::subagentCallLimit, LLMRunStopReason::inputTokenLimit, LLMRunStopReason::outputTokenLimit, LLMRunStopReason::totalTokenLimit, LLMRunStopReason::writeApprovalRequired, LLMRunStopReason::contextLimit => $stopReason,
            LLMRunStopReason::done, LLMRunStopReason::iterationCap, LLMRunStopReason::deadline, LLMRunStopReason::providerFailure, LLMRunStopReason::outputLimit, LLMRunStopReason::refusal => null,
        };
    }

}
