<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Closure;
use JsonSerializable;
use LogicException;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use stdClass;
use Throwable;

/** Enforces one agent run's context, model, tool, budget, deadline, and trace boundaries independently of its loop strategy. */
final class LLMAgentRuntime implements LLMAgentSession
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
    /** @var Dictionary<bool> */
    private Dictionary $advertisedSyntheticTools;
    /** @var Dictionary<LLMToolCall> */
    private Dictionary $pendingToolCalls;
    private int $iterations = 0;
    /** @var int<0, max> */
    private int $inputTokens = 0;
    /** @var int<0, max> */
    private int $outputTokens = 0;
    private bool $wasExecuted = false;
    private ?LLMAgentLoopOutcome $terminalOutcome = null;
    #[Override]
    public int $maxIterations {
        get => $this->environment->maxIterations;
    }
    #[Override]
    public bool $canSpawnSubagents {
        get => $this->environment->canSpawnSubagents;
    }
    #[Override]
    public ?string $systemPrompt {
        get => $this->request->systemPrompt;
    }
    #[Override]
    public ArrayClass $messages {
        get => clone $this->history;
    }
    #[Override]
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
        $this->advertisedSyntheticTools = new Dictionary();
        $this->pendingToolCalls = new Dictionary();
    }

    /**
     * Runs one orchestration strategy once and constructs its result exclusively from a runtime-owned state.
     *
     * @param LLMAgentLoop $loop The strategy allowed to make decisions through this runtime.
     * @return LLMRun The generated messages, accounted tokens and normalized stop condition.
     * @throws Throwable An unexpected fault from the strategy or one of the protected services.
     */
    public function execute(LLMAgentLoop $loop): LLMRun
    {
        if ($this->wasExecuted) {
            throw new LogicException("An agent runtime can only be executed once.");
        }
        $this->wasExecuted = true;
        $startedAt = $this->environment->clock->monotonicTime;
        try {
            $this->emit(new LLMRunStartedEvent($this->runContext, $this->environment->clock->timestamp, $this->history->count, $this->systemPrompt !== null));
            $outcome = $loop->run($this);
            $run = $this->runForOutcome($this->terminalOutcome ?? $outcome);
            $this->emitFinished($run, $startedAt);
            return $run;
        } catch (LLMRunInterruptedException $exception) {
            $outcome = $this->terminalOutcome ?? new LLMAgentLoopOutcome($exception->stopReason, $exception->isRetryable);
            $run = $this->runForOutcome($outcome);
            $this->emitFinished($run, $startedAt);
            return $run;
        } catch (LLMToolProviderException $exception) {
            $outcome = $this->terminalOutcome ?? new LLMAgentLoopOutcome(LLMRunStopReason::toolProviderFailure, $exception->isTransient);
            $run = $this->runForOutcome($outcome);
            $this->emitFinished($run, $startedAt);
            return $run;
        } catch (Throwable $exception) {
            $this->emit(new LLMRunFailedEvent($this->runContext, $this->environment->clock->timestamp, $exception::class, $exception->getMessage(), $this->inputTokens, $this->outputTokens, $this->environment->clock->monotonicTime - $startedAt), false);
            throw $exception;
        }
    }

    #[Override]
    public function completeTurn(?ArrayClass $syntheticTools = null): LLMTurn
    {
        $this->enforceActive();
        if (!$this->pendingToolCalls->isEmpty) {
            throw new LogicException("Every tool call from the previous model turn must receive a result before another turn begins.");
        }
        if ($this->iterations >= $this->maxIterations) {
            $this->interrupt(LLMRunStopReason::iterationCap, true);
        }
        if (($stopReason = $this->executionState->tokenStopReason($this->environment->executionPolicy, true)) !== null) {
            $this->interrupt($stopReason, true);
        }
        $this->enforceDeadline();
        $iteration = ++$this->iterations;
        $toolList = $this->toolList($syntheticTools);
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
        $this->recordTokens($turn->inputTokens, $turn->outputTokens);
        $this->append(new LLMMessage(LLMMessageRole::assistant, $turn->text, $turn->toolCalls, outputTokens: $turn->outputTokens, thinkingBlocks: $turn->thinkingBlocks, reasoningContent: $turn->reasoningContent));
        foreach ($turn->toolCalls as $toolCall) {
            if ($this->pendingToolCalls[$toolCall->id] !== null) {
                $exception = new LLMProviderException("The model returned duplicate tool-call identifiers.");
                $this->emit(new LLMModelTurnFailedEvent($this->runContext, $this->environment->clock->timestamp, $iteration, $exception::class, $exception->getMessage(), $this->environment->clock->monotonicTime - $turnStartedAt));
                $this->interrupt(LLMRunStopReason::providerFailure, false);
            }
            $this->pendingToolCalls[$toolCall->id] = $toolCall;
        }
        $this->emit(new LLMModelTurnFinishedEvent($this->runContext, $this->environment->clock->timestamp, $iteration, $turn->inputTokens, $turn->outputTokens, $turn->stopReason, $turn->toolCalls->count, $this->environment->clock->monotonicTime - $turnStartedAt));
        if (($stopReason = $this->executionState->tokenStopReason($this->environment->executionPolicy, false)) !== null) {
            $this->interrupt($stopReason, true);
        }
        if ($turn->stopReason === LLMTurnStopReason::toolUse && ($stopReason = $this->executionState->tokenStopReason($this->environment->executionPolicy, true)) !== null) {
            $this->interrupt($stopReason, true);
        }
        match ($turn->stopReason) {
            LLMTurnStopReason::outputLimit => $this->interrupt(LLMRunStopReason::outputLimit, true),
            LLMTurnStopReason::refusal => $this->interrupt(LLMRunStopReason::refusal, false),
            LLMTurnStopReason::completed, LLMTurnStopReason::toolUse => null,
        };
        return $turn;
    }

    #[Override]
    public function executeTool(LLMToolCall $call): LLMToolExecutionResult
    {
        return $this->performToolCall($call, false, true, function () use ($call): LLMAgentToolResult {
            $result = $this->environment->toolExecutor->execute($call, $this->deadline);
            return new LLMAgentToolResult($result->text, $result->isError);
        });
    }

    #[Override]
    public function rejectSyntheticToolCall(LLMToolCall $call, string $message): LLMToolExecutionResult
    {
        $this->enforceSyntheticCall($call);
        return $this->performToolCall($call, false, false, fn(): LLMAgentToolResult => new LLMAgentToolResult($message, true));
    }

    #[Override]
    public function executeSubagent(LLMToolCall $call, LLMAgentRunRequest $request, LLMAgentLoop $loop, Closure $transform): LLMToolExecutionResult
    {
        if (!$this->canSpawnSubagents) {
            throw new LogicException("This agent run cannot launch subagents.");
        }
        $this->enforceSyntheticCall($call);
        return $this->performToolCall($call, true, false, function () use ($request, $loop, $transform): LLMAgentToolResult {
            $runtime = new self($request, $this->environment->child(), $this->executionState, $this->deadline, $this->runContext->child(), $this);
            return $transform($runtime->execute($loop));
        });
    }

    /**
     * @param ArrayClass<ToolDescriptor>|null $syntheticTools
     * @return ArrayClass<ToolDescriptor>
     */
    private function toolList(?ArrayClass $syntheticTools): ArrayClass
    {
        $tools = $this->tools;
        /** @var Dictionary<bool> $syntheticNames */
        $syntheticNames = new Dictionary();
        foreach ($syntheticTools ?? new ArrayClass() as $tool) {
            if ($tools->contains(fn(ToolDescriptor $candidate): bool => $candidate->name === $tool->name) || $syntheticNames[$tool->name] === true) {
                throw new LogicException("Tool names must be unique within an agent turn: $tool->name.");
            }
            $syntheticNames[$tool->name] = true;
            $tools = $tools->appending($tool);
        }
        $this->advertisedSyntheticTools = $syntheticNames;
        return $tools;
    }

    /**
     * @param Closure(): LLMAgentToolResult $operation
     * @throws Throwable
     */
    private function performToolCall(LLMToolCall $call, bool $isSubagent, bool $isRealTool, Closure $operation): LLMToolExecutionResult
    {
        $this->enforceActive();
        $this->enforcePendingToolCall($call);
        $this->emit(new LLMToolCallStartedEvent($this->runContext, $this->environment->clock->timestamp, $this->iterations, $call->id, $call->name));
        $startedAt = $this->environment->clock->monotonicTime;
        $didFinish = false;
        try {
            $this->deadline->enforce();
            $isCacheable = false;
            if ($isRealTool && $this->environment->toolExecutor->contains($call)) {
                if (!$this->environment->toolExecutor->isReadOnly($call) && !$this->environment->executionPolicy->approvesWrite($call)) {
                    $this->finishInterruptedToolCall($call, LLMRunStopReason::writeApprovalRequired, LLMToolCallDisposition::denied, self::writeApprovalRequired, false, $startedAt, $didFinish);
                }
                $isCacheable = $this->environment->toolExecutor->isCacheable($call);
            }
            if (($stopReason = $this->executionState->consumeToolCall($this->environment->executionPolicy, $isSubagent)) !== null) {
                $this->finishInterruptedToolCall($call, $stopReason, LLMToolCallDisposition::budgetExceeded, $this->stopReasonText($stopReason), true, $startedAt, $didFinish);
            }
            $cacheKey = $this->toolCallCacheKey($call);
            $cached = $isCacheable ? $this->toolCallCache[$cacheKey] : null;
            if ($cached !== null) {
                $text = $this->repeatedCallText($cached);
                $this->finishToolCall($call, $text, false, LLMToolCallDisposition::cached, 0.0, $didFinish);
                return new LLMToolExecutionResult($text);
            }
            $outcome = $operation();
            $this->deadline->enforce();
            $disposition = $outcome->isError ? LLMToolCallDisposition::failed : LLMToolCallDisposition::executed;
            $this->finishToolCall($call, $outcome->text, $outcome->isError, $disposition, $this->environment->clock->monotonicTime - $startedAt, $didFinish);
            if (!$outcome->isError && $isCacheable) {
                $this->toolCallCache[$cacheKey] = $outcome->text;
            }
            if ($outcome->stopReason !== null) {
                $this->interrupt($outcome->stopReason, $outcome->isRetryable);
            }
            if (($stopReason = $this->executionState->tokenStopReason($this->environment->executionPolicy, true)) !== null) {
                $this->interrupt($stopReason, true);
            }
            return new LLMToolExecutionResult($outcome->text, $outcome->isError);
        } catch (LLMRunInterruptedException $exception) {
            throw $exception;
        } catch (LLMDeadlineExceededException) {
            $this->finishToolCall($call, self::deadlineExceeded, true, LLMToolCallDisposition::deadlineExceeded, $this->environment->clock->monotonicTime - $startedAt, $didFinish);
            $this->interrupt(LLMRunStopReason::deadline, true);
        } catch (LLMToolProviderException $exception) {
            if (!$didFinish) {
                $didFinish = true;
                $this->emit(new LLMToolCallFinishedEvent($this->runContext, $this->environment->clock->timestamp, $this->iterations, $call->id, $call->name, LLMToolCallDisposition::providerFailure, 0, $this->environment->clock->monotonicTime - $startedAt));
            }
            $this->interrupt(LLMRunStopReason::toolProviderFailure, $exception->isTransient);
        } catch (Throwable $exception) {
            if (!$didFinish) {
                $didFinish = true;
                $this->emit(new LLMToolCallFinishedEvent($this->runContext, $this->environment->clock->timestamp, $this->iterations, $call->id, $call->name, LLMToolCallDisposition::failed, 0, $this->environment->clock->monotonicTime - $startedAt));
            }
            throw $exception;
        }
    }

    /**
     * @throws Throwable
     */
    private function finishInterruptedToolCall(LLMToolCall $call, LLMRunStopReason $stopReason, LLMToolCallDisposition $disposition, string $text, ?bool $isRetryable, float $startedAt, bool &$didFinish): never
    {
        $this->finishToolCall($call, $text, true, $disposition, $this->environment->clock->monotonicTime - $startedAt, $didFinish);
        $this->interrupt($stopReason, $isRetryable);
    }

    /**
     * @throws Throwable
     */
    private function finishToolCall(LLMToolCall $call, string $text, bool $isError, LLMToolCallDisposition $disposition, float $duration, bool &$didFinish): void
    {
        $this->append(new LLMMessage(LLMMessageRole::tool, $text, toolCallId: $call->id, isError: $isError));
        $this->pendingToolCalls->removeValueForKey($call->id);
        $didFinish = true;
        $this->emit(new LLMToolCallFinishedEvent($this->runContext, $this->environment->clock->timestamp, $this->iterations, $call->id, $call->name, $disposition, strlen($text), $duration));
    }

    private function enforceSyntheticCall(LLMToolCall $call): void
    {
        if ($this->advertisedSyntheticTools[$call->name] !== true) {
            throw new LogicException("The strategy did not advertise a synthetic tool named $call->name on the latest turn.");
        }
    }

    private function enforcePendingToolCall(LLMToolCall $call): void
    {
        $pending = $this->pendingToolCalls[$call->id];
        if ($pending === null || $pending->name !== $call->name || $this->toolCallCacheKey($pending) !== $this->toolCallCacheKey($call)) {
            throw new LogicException("The model did not request this exact tool call in its latest turn.");
        }
    }

    private function append(LLMMessage $message): void
    {
        $this->history->append($message);
        $this->generatedMessages->append($message);
    }

    /**
     * @throws LLMRunInterruptedException
     */
    private function enforceActive(): void
    {
        if ($this->terminalOutcome !== null) {
            throw new LLMRunInterruptedException($this->terminalOutcome->stopReason, $this->terminalOutcome->isRetryable);
        }
    }

    /**
     * @throws LLMRunInterruptedException
     */
    private function interrupt(LLMRunStopReason $stopReason, ?bool $isRetryable): never
    {
        $this->terminalOutcome ??= new LLMAgentLoopOutcome($stopReason, $isRetryable);
        throw new LLMRunInterruptedException($this->terminalOutcome->stopReason, $this->terminalOutcome->isRetryable);
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

    private function runForOutcome(LLMAgentLoopOutcome $outcome): LLMRun
    {
        $run = new LLMRun(clone $this->generatedMessages, $this->inputTokens, $this->outputTokens, $outcome->stopReason, $outcome->isRetryable);
        if ($outcome->stopReason === LLMRunStopReason::done && !$run->isComplete) {
            throw new LogicException("An agent loop cannot complete without a conclusive assistant turn recorded by its session.");
        }
        return $run;
    }

    /** @throws Throwable */
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
        return hash("sha256", serialize([$toolCall->name, $this->canonicalToolValue($toolCall->arguments)]));
    }

    private function canonicalToolValue(mixed $value): array
    {
        if ($value instanceof Dictionary) {
            return ["dictionary", $value->keys->sort()->map(fn(string $key): array => [$key, $this->canonicalToolValue($value[$key])])->array];
        }
        if ($value instanceof ArrayClass) {
            return ["array", $value->map(fn(mixed $element): array => $this->canonicalToolValue($element))->array];
        }
        if ($value instanceof stdClass) {
            return $this->canonicalToolValue(Dictionary::dictionaryWithArray($value));
        }
        if ($value instanceof JsonSerializable) {
            return ["json", $value::class, $this->canonicalToolValue($value->jsonSerialize())];
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                return ["array", array_map($this->canonicalToolValue(...), $value)];
            }
            ksort($value);
            return ["dictionary", array_map($this->canonicalToolValue(...), $value)];
        }
        return [get_debug_type($value), $value];
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
