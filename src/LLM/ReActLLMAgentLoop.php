<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use Throwable;
use function Sabatier\Foundation\human_readable_value;

/**
 * Implements the framework's tool-using ReAct loop.
 *
 * Repeatedly calls the LLM client, executes any tool calls via an `LLMToolExecutor`, and feeds
 * results back into the conversation until the model explicitly completes the turn.
 * A response cut off by its output limit or refused by the model ends the run as incomplete
 * rather than being laundered into a conclusion merely because it contains no tool calls.
 * Returns an `LLMRun` containing only the messages generated during
 * this run (not the input history) and the total tokens consumed.
 *
 * The loop also stops when it runs out of iterations, runs past its time limit, exhausts a shared execution-policy budget, needs approval for a write, or the provider fails after the client has exhausted its retries. All are reported as incomplete (`LLMRun::$isComplete`) and told apart by `LLMRun::$stopReason`; a caller that treats them alike presents unfinished work as a conclusion. A provider failure ends the run rather than propagating, so the messages and tokens already paid for survive it, and `LLMRun::$isRetryable` carries whether another attempt is worth making — a rate limit clears on its own, a rejected key does not.
 *
 * Tool failures are resolved by the executor, not here. A correctable mistake — the model
 * mis-called a tool — comes back as a failed `LLMToolExecutionResult` and is fed to the model as an
 * error-flagged tool message so the loop can self-correct; it is not cached, since the same
 * call may succeed once corrected. A real program fault propagates out of the executor and
 * aborts the run for the caller to handle.
 *
 * Successful calls to a read-only tool are cached for the length of the run, keyed on the tool name and arguments regardless of the order the model emitted them in, so a model that loops on one call is told what it already got back instead of paying for the call again. A tool that writes is never cached: two identical calls are two mutations the model asked for, and answering the second from the cache would silently drop one.
 *
 * An `LLMRunObserver` receives immutable events while the loop runs. `LLMRun` reports what a finished run cost and why it stopped; the events report what only the loop can see — span boundaries, iteration numbers, durations, cache outcomes, and subagent ancestry — before the run returns. They contain scalar snapshots rather than mutable loop objects and omit prompts, tool arguments, and results. Subagents inherit the observer and report under their own `LLMRunContext`, naming the parent that launched them. Observer delivery is synchronous and best-effort by default, so a broken telemetry sink does not change the agent result; strict delivery is opt-in.
 */
final class ReActLLMAgentLoop implements LLMAgentLoop
{
    private const string subagentToolName = "run_subagent";
    private const string subagentNoAnswer = "The subagent produced no answer.";
    private const string subagentTaskRequired = "Subagent task is required.";
    private const string subagentIncomplete = "The subagent ran out of iterations before finishing. Narrow the task and try again.";
    private const string subagentProviderFailure = "The subagent could not reach the model provider. Retrying may work.";
    private const string subagentProviderRefused = "The subagent could not reach the model provider, and the failure will repeat: it needs the server's configuration fixed, not another attempt. Report it rather than retrying.";
    private const string subagentToolProviderFailure = "The subagent could not reach its tool provider. Retrying may work.";
    private const string subagentToolProviderRefused = "The subagent's tool provider rejected or could not understand the request, and retrying it unchanged will repeat the failure.";
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
    private const string deadlineExceeded = "The agent deadline expired before this tool returned a usable result.";
    private const int subagentMaxIterations = 8;
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
    /**
     * @throws Throwable
     */
    #[Override]
    public function run(LLMAgentRunRequest $request, LLMAgentEnvironment $environment): LLMRun
    {
        return $this->runWithState($request, $environment, new LLMExecutionState(), new LLMRunContext(), new LLMExecutionDeadline($environment->clock, $environment->timeLimit));
    }

    /**
     * @throws Throwable
     */
    private function runWithState(LLMAgentRunRequest $request, LLMAgentEnvironment $environment, LLMExecutionState $executionState, LLMRunContext $runContext, LLMExecutionDeadline $deadline): LLMRun
    {
        $runStartedAt = $environment->clock->monotonicTime;
        $messages = $request->messages;
        $systemPrompt = $request->systemPrompt;
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
        try {
            $this->emit(new LLMRunStartedEvent($runContext, $environment->clock->timestamp, $messages->count, $systemPrompt !== null), $environment);
            $toolList = $this->toolList($environment);
            $history = $messages;
            while ($iterations++ < $environment->maxIterations) {
                if (($tokenStopReason = $executionState->tokenStopReason($environment->executionPolicy, true)) !== null) {
                    $stopReason = $tokenStopReason;
                    break;
                }
                if ($deadline->hasExpired) {
                    $stopReason = LLMRunStopReason::deadline;
                    break;
                }
                $this->emit(new LLMContextAssemblyStartedEvent($runContext, $environment->clock->timestamp, $iterations, $history->count), $environment);
                $contextStartedAt = $environment->clock->monotonicTime;
                try {
                    $context = $environment->contextAssembler->assemble($history, $toolList, $systemPrompt);
                } catch (Throwable $exception) {
                    $this->emit(new LLMContextAssemblyFailedEvent($runContext, $environment->clock->timestamp, $iterations, $exception::class, $exception->getMessage(), $environment->clock->monotonicTime - $contextStartedAt), $environment);
                    throw $exception;
                }
                $this->emit(new LLMContextAssemblyFinishedEvent($runContext, $environment->clock->timestamp, $iterations, $context->messages->count, $context->omittedMessageCount, $context->wasCompacted, $context->isWithinLimit, $environment->clock->monotonicTime - $contextStartedAt), $environment);
                if (!$context->isWithinLimit) {
                    $stopReason = LLMRunStopReason::contextLimit;
                    $isRetryable = false;
                    break;
                }
                try {
                    $deadline->enforce();
                } catch (LLMDeadlineExceededException) {
                    $stopReason = LLMRunStopReason::deadline;
                    break;
                }
                $this->emit(new LLMModelTurnStartedEvent($runContext, $environment->clock->timestamp, $iterations, $context->messages->count, $context->omittedMessageCount, $context->wasCompacted), $environment);
                $turnStartedAt = $environment->clock->monotonicTime;
                try {
                    $turn = $environment->client->complete($context->messages, $toolList, $context->systemPrompt, $deadline);
                    $deadline->enforce();
                } catch (LLMDeadlineExceededException $exception) {
                    $this->emit(new LLMModelTurnFailedEvent($runContext, $environment->clock->timestamp, $iterations, $exception::class, $exception->getMessage(), $environment->clock->monotonicTime - $turnStartedAt), $environment);
                    $stopReason = LLMRunStopReason::deadline;
                    break;
                } catch (LLMProviderException $exception) {
                    $this->emit(new LLMModelTurnFailedEvent($runContext, $environment->clock->timestamp, $iterations, $exception::class, $exception->getMessage(), $environment->clock->monotonicTime - $turnStartedAt), $environment);
                    $stopReason = LLMRunStopReason::providerFailure;
                    $isRetryable = $exception->isTransient;
                    break;
                } catch (Throwable $exception) {
                    $this->emit(new LLMModelTurnFailedEvent($runContext, $environment->clock->timestamp, $iterations, $exception::class, $exception->getMessage(), $environment->clock->monotonicTime - $turnStartedAt), $environment);
                    throw $exception;
                }
                $this->emit(new LLMModelTurnFinishedEvent($runContext, $environment->clock->timestamp, $iterations, $turn->inputTokens, $turn->outputTokens, $turn->stopReason, $turn->toolCalls->count, $environment->clock->monotonicTime - $turnStartedAt), $environment);
                $totalInputTokens += $turn->inputTokens;
                $totalOutputTokens += $turn->outputTokens;
                $executionState->recordTokens($turn->inputTokens, $turn->outputTokens);
                $assistantMsg = new LLMMessage(LLMMessageRole::assistant, $turn->text, $turn->toolCalls, outputTokens: $turn->outputTokens, thinkingBlocks: $turn->thinkingBlocks, reasoningContent: $turn->reasoningContent);
                $history->append($assistantMsg);
                $newMessages->append($assistantMsg);
                if (($tokenStopReason = $executionState->tokenStopReason($environment->executionPolicy, false)) !== null) {
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
                if (($tokenStopReason = $executionState->tokenStopReason($environment->executionPolicy, true)) !== null) {
                    $stopReason = $tokenStopReason;
                    break;
                }
                foreach ($turn->toolCalls as $toolCall) {
                    $this->emit(new LLMToolCallStartedEvent($runContext, $environment->clock->timestamp, $iterations, $toolCall->id, $toolCall->name), $environment);
                    $toolStartedAt = $environment->clock->monotonicTime;
                    $isSubagent = $environment->canSpawnSubagents && $toolCall->name === self::subagentToolName;
                    /** @var array{LLMRunStopReason, LLMToolCallDisposition, string, bool}|null $interruption */
                    $interruption = null;
                    try {
                        $deadline->enforce();
                    } catch (LLMDeadlineExceededException) {
                        $interruption = [LLMRunStopReason::deadline, LLMToolCallDisposition::deadlineExceeded, self::deadlineExceeded, true];
                    }
                    if ($interruption === null && !$isSubagent && $environment->toolExecutor->contains($toolCall) && !$environment->toolExecutor->isReadOnly($toolCall) && !$environment->executionPolicy->approvesWrite($toolCall)) {
                        $interruption = [LLMRunStopReason::writeApprovalRequired, LLMToolCallDisposition::denied, self::writeApprovalRequired, false];
                    }
                    if ($interruption === null && ($callStopReason = $executionState->consumeToolCall($environment->executionPolicy, $isSubagent)) !== null) {
                        $interruption = [$callStopReason, LLMToolCallDisposition::budgetExceeded, $this->stopReasonText($callStopReason), true];
                    }
                    if ($interruption !== null) {
                        [$stopReason, $disposition, $text, $isRetryable] = $interruption;
                        $toolMsg = new LLMMessage(LLMMessageRole::tool, $text, toolCallId: $toolCall->id, isError: true);
                        $history->append($toolMsg);
                        $newMessages->append($toolMsg);
                        $this->emit(new LLMToolCallFinishedEvent($runContext, $environment->clock->timestamp, $iterations, $toolCall->id, $toolCall->name, $disposition, strlen($text), $environment->clock->monotonicTime - $toolStartedAt), $environment);
                        break 2;
                    }
                    $cacheKey = $this->toolCallCacheKey($toolCall);
                    $isError = false;
                    $propagatedStopReason = null;
                    $propagatedRetryable = null;
                    $cached = $toolCallCache[$cacheKey];
                    if ($cached !== null) {
                        $text = $this->repeatedCallText($cached);
                        $disposition = LLMToolCallDisposition::cached;
                        $duration = 0.0;
                    } else {
                        try {
                            if ($isSubagent) {
                                $subRun = $this->runSubagent($toolCall->arguments, $systemPrompt, $executionState, $runContext, $environment, $deadline);
                                $totalInputTokens += $subRun->inputTokens;
                                $totalOutputTokens += $subRun->outputTokens;
                                $answer = $this->subagentResultText($subRun);
                                $text = $answer ?? $this->subagentFailureText($subRun);
                                $isError = $answer === null;
                                $propagatedStopReason = $this->propagatedStopReason($subRun->stopReason);
                                $propagatedRetryable = $subRun->isRetryable;
                            } else {
                                $result = $environment->toolExecutor->execute($toolCall, $deadline);
                                $deadline->enforce();
                                $text = $result->text;
                                $isError = $result->isError;
                            }
                        } catch (LLMDeadlineExceededException) {
                            $text = self::deadlineExceeded;
                            $isError = true;
                            $propagatedStopReason = LLMRunStopReason::deadline;
                            $propagatedRetryable = true;
                        } catch (LLMToolProviderException $exception) {
                            $this->emit(new LLMToolCallFinishedEvent($runContext, $environment->clock->timestamp, $iterations, $toolCall->id, $toolCall->name, LLMToolCallDisposition::providerFailure, 0, $environment->clock->monotonicTime - $toolStartedAt), $environment);
                            $stopReason = LLMRunStopReason::toolProviderFailure;
                            $isRetryable = $exception->isTransient;
                            break 2;
                        } catch (Throwable $exception) {
                            $this->emit(new LLMToolCallFinishedEvent($runContext, $environment->clock->timestamp, $iterations, $toolCall->id, $toolCall->name, LLMToolCallDisposition::failed, 0, $environment->clock->monotonicTime - $toolStartedAt), $environment);
                            throw $exception;
                        }
                        $disposition = $propagatedStopReason === LLMRunStopReason::deadline ? LLMToolCallDisposition::deadlineExceeded : ($isError ? LLMToolCallDisposition::failed : LLMToolCallDisposition::executed);
                        $duration = $environment->clock->monotonicTime - $toolStartedAt;
                        if (!$isError && $this->isCacheable($toolCall, $environment)) {
                            $toolCallCache[$cacheKey] = $text;
                        }
                    }
                    $this->emit(new LLMToolCallFinishedEvent($runContext, $environment->clock->timestamp, $iterations, $toolCall->id, $toolCall->name, $disposition, strlen($text), $duration), $environment);
                    $toolMsg = new LLMMessage(LLMMessageRole::tool, $text, toolCallId: $toolCall->id, isError: $isError);
                    $history->append($toolMsg);
                    $newMessages->append($toolMsg);
                    if ($propagatedStopReason !== null) {
                        $stopReason = $propagatedStopReason;
                        $isRetryable = $propagatedRetryable;
                        break 2;
                    }
                    if (($tokenStopReason = $executionState->tokenStopReason($environment->executionPolicy, true)) !== null) {
                        $stopReason = $tokenStopReason;
                        break 2;
                    }
                }
            }
            $run = new LLMRun($newMessages, $totalInputTokens, $totalOutputTokens, $stopReason, $isRetryable);
            $this->emit(new LLMRunFinishedEvent($runContext, $environment->clock->timestamp, $run->stopReason, $run->isComplete, $run->isRetryable, $run->messages->count, $run->inputTokens, $run->outputTokens, $environment->clock->monotonicTime - $runStartedAt), $environment);
            return $run;
        } catch (LLMToolProviderException $exception) {
            $run = new LLMRun($newMessages, $totalInputTokens, $totalOutputTokens, LLMRunStopReason::toolProviderFailure, $exception->isTransient);
            $this->emit(new LLMRunFinishedEvent($runContext, $environment->clock->timestamp, $run->stopReason, $run->isComplete, $run->isRetryable, $run->messages->count, $run->inputTokens, $run->outputTokens, $environment->clock->monotonicTime - $runStartedAt), $environment);
            return $run;
        } catch (Throwable $exception) {
            $this->emit(new LLMRunFailedEvent($runContext, $environment->clock->timestamp, $exception::class, $exception->getMessage(), $totalInputTokens, $totalOutputTokens, $environment->clock->monotonicTime - $runStartedAt), $environment, false);
            throw $exception;
        }
    }

    /** @return ArrayClass<ToolDescriptor> */
    private function toolList(LLMAgentEnvironment $environment): ArrayClass
    {
        return $environment->canSpawnSubagents ? $environment->toolExecutor->tools->appending($this->subagentToolDescriptor) : $environment->toolExecutor->tools;
    }

    /**
     * @throws Throwable
     */
    private function emit(LLMRunEvent $event, LLMAgentEnvironment $environment, bool $honorFailurePolicy = true): void
    {
        if ($environment->observer === null) {
            return;
        }
        try {
            $environment->observer->observe($event);
        } catch (Throwable $exception) {
            if ($honorFailurePolicy && $environment->observerFailurePolicy === LLMRunObserverFailurePolicy::strict) {
                throw $exception;
            }
            error_log("LLM run observer failed: {$exception->getMessage()}");
        }
    }

    /**
     * Whether a repeated call to this tool may be answered from the run's cache instead of executed again.
     *
     * Only a tool that declares itself read-only qualifies. A tool that writes must run every time it is called: two identical `create` calls are two rows the model asked for, and serving the second from the cache silently drops the write. The synthetic subagent tool is never cacheable either — it runs a whole nested conversation, and the same task posed twice is not a repeated read.
     */
    private function isCacheable(LLMToolCall $call, LLMAgentEnvironment $environment): bool
    {
        return $call->name !== self::subagentToolName && $environment->toolExecutor->isCacheable($call);
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
    private function runSubagent(Dictionary $arguments, ?string $parentSystemPrompt, LLMExecutionState $executionState, LLMRunContext $runContext, LLMAgentEnvironment $environment, LLMExecutionDeadline $deadline): LLMRun
    {
        $task = trim((string)$arguments["task"]);
        if ($task === "") {
            return new LLMRun(new ArrayClass());
        }
        $context = trim((string)$arguments["context"]);
        $content = $context === "" ? $task : "Task:\n$task\n\nContext:\n$context";
        $subagentEnvironment = new LLMAgentEnvironment($environment->client, $environment->toolExecutor, self::subagentMaxIterations, false, null, $environment->executionPolicy, $environment->contextAssembler, $environment->observer, $environment->observerFailurePolicy, $environment->clock);
        return $this->runWithState(new LLMAgentRunRequest(new ArrayClass([new LLMMessage(LLMMessageRole::user, $content)]), $parentSystemPrompt), $subagentEnvironment, $executionState, $runContext->child(), $deadline);
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
            LLMRunStopReason::toolProviderFailure => $run->isRetryable === false ? self::subagentToolProviderRefused : self::subagentToolProviderFailure,
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
            LLMRunStopReason::done, LLMRunStopReason::iterationCap, LLMRunStopReason::deadline, LLMRunStopReason::providerFailure, LLMRunStopReason::toolProviderFailure, LLMRunStopReason::outputLimit, LLMRunStopReason::refusal, LLMRunStopReason::writeApprovalRequired, LLMRunStopReason::contextLimit => "The agent run stopped before this tool could execute.",
        };
    }

    private function propagatedStopReason(LLMRunStopReason $stopReason): ?LLMRunStopReason
    {
        return match ($stopReason) {
            LLMRunStopReason::toolCallLimit, LLMRunStopReason::subagentCallLimit, LLMRunStopReason::inputTokenLimit, LLMRunStopReason::outputTokenLimit, LLMRunStopReason::totalTokenLimit, LLMRunStopReason::writeApprovalRequired, LLMRunStopReason::contextLimit, LLMRunStopReason::deadline, LLMRunStopReason::toolProviderFailure => $stopReason,
            LLMRunStopReason::done, LLMRunStopReason::iterationCap, LLMRunStopReason::providerFailure, LLMRunStopReason::outputLimit, LLMRunStopReason::refusal => null,
        };
    }

}
