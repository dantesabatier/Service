<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Closure;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use Throwable;
use function Sabatier\Foundation\human_readable_value;

/** Enforces one agent run's context, model, tool, budget, deadline and trace boundaries independently of its loop strategy. */
final class LLMAgentRuntime
{
    private const string writeApprovalRequired = "This tool changes state and requires explicit user approval before it can run.";
    private const string toolCallLimit = "The shared tool-call budget is exhausted.";
    private const string subagentCallLimit = "The shared subagent budget is exhausted.";
    private const string inputTokenLimit = "The shared input-token budget is exhausted.";
    private const string outputTokenLimit = "The shared output-token budget is exhausted.";
    private const string totalTokenLimit = "The shared total-token budget is exhausted.";
    private const string deadlineExceeded = "The agent deadline expired before this tool returned a usable result.";
    private readonly LLMExecutionState $executionState;
    private readonly LLMExecutionDeadline $deadline;
    private readonly LLMRunContext $runContext;
    private readonly ?self $parent;
    /** @var ArrayClass<LLMMessage> */
    private ArrayClass $history;
    /** @var ArrayClass<LLMMessage> */
    private ArrayClass $generatedMessages;
    /** @var Dictionary<string> */
    private Dictionary $toolCallCache;
    private int $iterations = 0;
    /** @var int<0, max> */
    private int $inputTokens = 0;
    /** @var int<0, max> */
    private int $outputTokens = 0;
    /** @var int Maximum model turns this run may open. */
    public int $maxIterations {
        get => $this->environment->maxIterations;
    }
    /** @var bool Whether this run may advertise and execute a synthetic subagent tool. */
    public bool $canSpawnSubagents {
        get => $this->environment->canSpawnSubagents;
    }
    /** @var string|null The system prompt inherited by this run. */
    public ?string $systemPrompt {
        get => $this->request->systemPrompt;
    }
    /** @var ArrayClass<LLMMessage> A disposable snapshot of the complete history accumulated by this run. */
    public ArrayClass $messages {
        get => clone $this->history;
    }
    /** @var ArrayClass<ToolDescriptor> The real provider-neutral tools available through the protected executor. */
    public ArrayClass $tools {
        get => $this->environment->toolExecutor->tools;
    }

    /**
     * @param LLMAgentRunRequest $request The isolated request this runtime owns.
     * @param LLMAgentEnvironment $environment The services and limits hidden from the loop strategy.
     * @param LLMExecutionState|null $executionState Shared consumption state, or `null` for a root run.
     * @param LLMExecutionDeadline|null $deadline Shared deadline, or `null` to derive one for a root run.
     * @param LLMRunContext|null $runContext Trace identity, or `null` to create a root identity.
     * @param self|null $parent The parent runtime whose token totals include this run, or `null` for a root.
     * @internal
     */
    public function __construct(private readonly LLMAgentRunRequest $request, private readonly LLMAgentEnvironment $environment, ?LLMExecutionState $executionState = null, ?LLMExecutionDeadline $deadline = null, ?LLMRunContext $runContext = null, ?self $parent = null)
    {
        $this->executionState = $executionState ?? new LLMExecutionState();
        $this->deadline = $deadline ?? new LLMExecutionDeadline($environment->clock, $environment->timeLimit);
        $this->runContext = $runContext ?? new LLMRunContext();
        $this->parent = $parent;
        $this->history = $request->messages;
        $this->generatedMessages = new ArrayClass();
        $this->toolCallCache = new Dictionary();
    }

    /**
     * Runs one orchestration strategy inside the protected lifecycle and normalizes policy interruptions into an `LLMRun`.
     *
     * @param LLMAgentLoop $loop The strategy allowed to make model and tool decisions through this runtime.
     * @return LLMRun The generated messages, accounted tokens and normalized stop condition.
     * @throws Throwable An unexpected fault from the strategy or one of the protected services.
     */
    public function execute(LLMAgentLoop $loop): LLMRun
    {
        $startedAt = $this->environment->clock->monotonicTime;
        try {
            $this->emit(new LLMRunStartedEvent($this->runContext, $this->environment->clock->timestamp, $this->history->count, $this->systemPrompt !== null));
            $run = $loop->run($this);
            $this->emitFinished($run, $startedAt);
            return $run;
        } catch (LLMRunInterruptedException $exception) {
            $run = $this->finish($exception->stopReason, $exception->isRetryable);
            $this->emitFinished($run, $startedAt);
            return $run;
        } catch (LLMToolProviderException $exception) {
            $run = $this->finish(LLMRunStopReason::toolProviderFailure, $exception->isTransient);
            $this->emitFinished($run, $startedAt);
            return $run;
        } catch (Throwable $exception) {
            $this->emit(new LLMRunFailedEvent($this->runContext, $this->environment->clock->timestamp, $exception::class, $exception->getMessage(), $this->inputTokens, $this->outputTokens, $this->environment->clock->monotonicTime - $startedAt), false);
            throw $exception;
        }
    }

    /**
     * Assembles context, performs one guarded provider turn, records its usage and appends its assistant message.
     *
     * @param ArrayClass<ToolDescriptor>|null $tools The catalogue advertised by the loop, or `null` for the runtime's real tools.
     * @return LLMTurn The normalized provider turn.
     * @throws Throwable An unexpected context or provider fault.
     */
    public function completeTurn(?ArrayClass $tools = null): LLMTurn
    {
        if ($this->iterations >= $this->maxIterations) {
            $this->interrupt(LLMRunStopReason::iterationCap, true);
        }
        if (($stopReason = $this->executionState->tokenStopReason($this->environment->executionPolicy, true)) !== null) {
            $this->interrupt($stopReason, true);
        }
        $this->enforceDeadline();
        $iteration = ++$this->iterations;
        $toolList = $tools ?? $this->tools;
        $this->emit(new LLMContextAssemblyStartedEvent($this->runContext, $this->environment->clock->timestamp, $iteration, $this->history->count));
        $contextStartedAt = $this->environment->clock->monotonicTime;
        try {
            $context = $this->environment->contextAssembler->assemble($this->history, $toolList, $this->systemPrompt);
        } catch (Throwable $exception) {
            $this->emit(new LLMContextAssemblyFailedEvent($this->runContext, $this->environment->clock->timestamp, $iteration, $exception::class, $exception->getMessage(), $this->environment->clock->monotonicTime - $contextStartedAt));
            throw $exception;
        }
        $this->emit(new LLMContextAssemblyFinishedEvent($this->runContext, $this->environment->clock->timestamp, $iteration, $context->messages->count, $context->omittedMessageCount, $context->wasCompacted, $context->isWithinLimit, $this->environment->clock->monotonicTime - $contextStartedAt));
        if (!$context->isWithinLimit) {
            $this->interrupt(LLMRunStopReason::contextLimit, false);
        }
        $this->enforceDeadline();
        $this->emit(new LLMModelTurnStartedEvent($this->runContext, $this->environment->clock->timestamp, $iteration, $context->messages->count, $context->omittedMessageCount, $context->wasCompacted));
        $turnStartedAt = $this->environment->clock->monotonicTime;
        try {
            $turn = $this->environment->client->complete($context->messages, $toolList, $context->systemPrompt, $this->deadline);
            $this->deadline->enforce();
        } catch (LLMDeadlineExceededException $exception) {
            $this->emit(new LLMModelTurnFailedEvent($this->runContext, $this->environment->clock->timestamp, $iteration, $exception::class, $exception->getMessage(), $this->environment->clock->monotonicTime - $turnStartedAt));
            $this->interrupt(LLMRunStopReason::deadline, true);
        } catch (LLMProviderException $exception) {
            $this->emit(new LLMModelTurnFailedEvent($this->runContext, $this->environment->clock->timestamp, $iteration, $exception::class, $exception->getMessage(), $this->environment->clock->monotonicTime - $turnStartedAt));
            $this->interrupt(LLMRunStopReason::providerFailure, $exception->isTransient);
        } catch (Throwable $exception) {
            $this->emit(new LLMModelTurnFailedEvent($this->runContext, $this->environment->clock->timestamp, $iteration, $exception::class, $exception->getMessage(), $this->environment->clock->monotonicTime - $turnStartedAt));
            throw $exception;
        }
        $this->emit(new LLMModelTurnFinishedEvent($this->runContext, $this->environment->clock->timestamp, $iteration, $turn->inputTokens, $turn->outputTokens, $turn->stopReason, $turn->toolCalls->count, $this->environment->clock->monotonicTime - $turnStartedAt));
        $this->recordTokens($turn->inputTokens, $turn->outputTokens);
        $this->append(new LLMMessage(LLMMessageRole::assistant, $turn->text, $turn->toolCalls, outputTokens: $turn->outputTokens, thinkingBlocks: $turn->thinkingBlocks, reasoningContent: $turn->reasoningContent));
        if (($stopReason = $this->executionState->tokenStopReason($this->environment->executionPolicy, false)) !== null) {
            $this->interrupt($stopReason, true);
        }
        if ($turn->stopReason === LLMTurnStopReason::toolUse && ($stopReason = $this->executionState->tokenStopReason($this->environment->executionPolicy, true)) !== null) {
            $this->interrupt($stopReason, true);
        }
        return $turn;
    }

    /**
     * Executes one real or loop-supplied synthetic tool operation after applying approval, budget, deadline, cache and trace policy.
     *
     * @param LLMToolCall $call The invocation requested by the latest model turn.
     * @param bool $isSubagent Whether this invocation consumes the shared subagent budget and bypasses real-tool classification.
     * @param Closure(): LLMAgentToolResult|null $operation A synthetic operation supplied by the loop, or `null` to dispatch through the real executor.
     * @return LLMToolExecutionResult The result already appended to this runtime's history.
     * @throws Throwable An unexpected tool fault.
     */
    public function executeTool(LLMToolCall $call, bool $isSubagent = false, ?Closure $operation = null): LLMToolExecutionResult
    {
        $this->emit(new LLMToolCallStartedEvent($this->runContext, $this->environment->clock->timestamp, $this->iterations, $call->id, $call->name));
        $startedAt = $this->environment->clock->monotonicTime;
        try {
            $this->deadline->enforce();
        } catch (LLMDeadlineExceededException) {
            $this->interruptTool($call, LLMRunStopReason::deadline, LLMToolCallDisposition::deadlineExceeded, self::deadlineExceeded, true, $startedAt);
        }
        if (!$isSubagent && $this->environment->toolExecutor->contains($call) && !$this->environment->toolExecutor->isReadOnly($call) && !$this->environment->executionPolicy->approvesWrite($call)) {
            $this->interruptTool($call, LLMRunStopReason::writeApprovalRequired, LLMToolCallDisposition::denied, self::writeApprovalRequired, false, $startedAt);
        }
        if (($stopReason = $this->executionState->consumeToolCall($this->environment->executionPolicy, $isSubagent)) !== null) {
            $this->interruptTool($call, $stopReason, LLMToolCallDisposition::budgetExceeded, $this->stopReasonText($stopReason), true, $startedAt);
        }
        $cacheKey = $this->toolCallCacheKey($call);
        $cached = $this->toolCallCache[$cacheKey];
        if ($cached !== null) {
            $text = $this->repeatedCallText($cached);
            $this->appendToolResult($call, $text, false, LLMToolCallDisposition::cached, 0.0);
            return new LLMToolExecutionResult($text);
        }
        try {
            if ($operation !== null) {
                $outcome = $operation();
            } else {
                $result = $this->environment->toolExecutor->execute($call, $this->deadline);
                $outcome = new LLMAgentToolResult($result->text, $result->isError);
            }
            $this->deadline->enforce();
        } catch (LLMDeadlineExceededException) {
            $outcome = new LLMAgentToolResult(self::deadlineExceeded, true, LLMRunStopReason::deadline, true);
        } catch (LLMToolProviderException $exception) {
            $this->emit(new LLMToolCallFinishedEvent($this->runContext, $this->environment->clock->timestamp, $this->iterations, $call->id, $call->name, LLMToolCallDisposition::providerFailure, 0, $this->environment->clock->monotonicTime - $startedAt));
            $this->interrupt(LLMRunStopReason::toolProviderFailure, $exception->isTransient);
        } catch (Throwable $exception) {
            $this->emit(new LLMToolCallFinishedEvent($this->runContext, $this->environment->clock->timestamp, $this->iterations, $call->id, $call->name, LLMToolCallDisposition::failed, 0, $this->environment->clock->monotonicTime - $startedAt));
            throw $exception;
        }
        $disposition = $outcome->stopReason === LLMRunStopReason::deadline ? LLMToolCallDisposition::deadlineExceeded : ($outcome->isError ? LLMToolCallDisposition::failed : LLMToolCallDisposition::executed);
        $this->appendToolResult($call, $outcome->text, $outcome->isError, $disposition, $this->environment->clock->monotonicTime - $startedAt);
        if (!$outcome->isError && $operation === null && $this->environment->toolExecutor->isCacheable($call)) {
            $this->toolCallCache[$cacheKey] = $outcome->text;
        }
        if ($outcome->stopReason !== null) {
            $this->interrupt($outcome->stopReason, $outcome->isRetryable);
        }
        if (($stopReason = $this->executionState->tokenStopReason($this->environment->executionPolicy, true)) !== null) {
            $this->interrupt($stopReason, true);
        }
        return new LLMToolExecutionResult($outcome->text, $outcome->isError);
    }

    /**
     * Creates a nested runtime sharing this run tree's provider, executor, policy, consumption state and deadline.
     *
     * @param LLMAgentRunRequest $request The isolated child request.
     * @param int $maxIterations Maximum model turns the child may open.
     * @param bool $canSpawnSubagents Whether the child may delegate again.
     */
    public function child(LLMAgentRunRequest $request, int $maxIterations, bool $canSpawnSubagents = false): self
    {
        $environment = new LLMAgentEnvironment($this->environment->client, $this->environment->toolExecutor, $maxIterations, $canSpawnSubagents, null, $this->environment->executionPolicy, $this->environment->contextAssembler, $this->environment->observer, $this->environment->observerFailurePolicy, $this->environment->clock);
        return new self($request, $environment, $this->executionState, $this->deadline, $this->runContext->child(), $this);
    }

    /**
     * Builds the current run result from messages and usage recorded through this runtime.
     *
     * @param LLMRunStopReason $stopReason Why the strategy has finished.
     * @param bool|null $isRetryable Whether repeating an incomplete run may succeed.
     */
    public function finish(LLMRunStopReason $stopReason = LLMRunStopReason::done, ?bool $isRetryable = null): LLMRun
    {
        return new LLMRun(clone $this->generatedMessages, $this->inputTokens, $this->outputTokens, $stopReason, $isRetryable);
    }

    private function append(LLMMessage $message): void
    {
        $this->history->append($message);
        $this->generatedMessages->append($message);
    }

    /**
     * @throws Throwable
     */
    private function appendToolResult(LLMToolCall $call, string $text, bool $isError, LLMToolCallDisposition $disposition, float $duration): void
    {
        $this->emit(new LLMToolCallFinishedEvent($this->runContext, $this->environment->clock->timestamp, $this->iterations, $call->id, $call->name, $disposition, strlen($text), $duration));
        $this->append(new LLMMessage(LLMMessageRole::tool, $text, toolCallId: $call->id, isError: $isError));
    }

    /**
     * @throws Throwable
     */
    private function interruptTool(LLMToolCall $call, LLMRunStopReason $stopReason, LLMToolCallDisposition $disposition, string $text, ?bool $isRetryable, float $startedAt): never
    {
        $this->appendToolResult($call, $text, true, $disposition, $this->environment->clock->monotonicTime - $startedAt);
        $this->interrupt($stopReason, $isRetryable);
    }

    /**
     * @throws LLMRunInterruptedException
     */
    private function interrupt(LLMRunStopReason $stopReason, ?bool $isRetryable): never
    {
        throw new LLMRunInterruptedException($stopReason, $isRetryable);
    }

    /**
     * @throws LLMRunInterruptedException
     */
    private function enforceDeadline(): void
    {
        try {
            $this->deadline->enforce();
        } catch (LLMDeadlineExceededException) {
            $this->interrupt(LLMRunStopReason::deadline, true);
        }
    }

    /**
     * @param int<0, max> $inputTokens
     * @param int<0, max> $outputTokens
     */
    private function recordTokens(int $inputTokens, int $outputTokens): void
    {
        $this->executionState->recordTokens($inputTokens, $outputTokens);
        $runtime = $this;
        while ($runtime !== null) {
            $runtime->inputTokens += $inputTokens;
            $runtime->outputTokens += $outputTokens;
            $runtime = $runtime->parent;
        }
    }

    /**
     * @throws Throwable
     */
    private function emitFinished(LLMRun $run, float $startedAt): void
    {
        $this->emit(new LLMRunFinishedEvent($this->runContext, $this->environment->clock->timestamp, $run->stopReason, $run->isComplete, $run->isRetryable, $run->messages->count, $run->inputTokens, $run->outputTokens, $this->environment->clock->monotonicTime - $startedAt));
    }

    /** @throws Throwable */
    private function emit(LLMRunEvent $event, bool $honorFailurePolicy = true): void
    {
        if ($this->environment->observer === null) {
            return;
        }
        try {
            $this->environment->observer->observe($event);
        } catch (Throwable $exception) {
            if ($honorFailurePolicy && $this->environment->observerFailurePolicy === LLMRunObserverFailurePolicy::strict) {
                throw $exception;
            }
            error_log("LLM run observer failed: {$exception->getMessage()}");
        }
    }

    private function toolCallCacheKey(LLMToolCall $toolCall): string
    {
        $arguments = $toolCall->arguments;
        $canonical = $arguments->keys->sort()->map(fn(string $key): string => sprintf("%s: %s", $key, human_readable_value($arguments[$key])))->join(", ");
        return md5("$toolCall->name($canonical)");
    }

    private function repeatedCallText(string $cached): string
    {
        return "Do not call this tool with these arguments again — you already did, and it returned:\n\n$cached";
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
}
