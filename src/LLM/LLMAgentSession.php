<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Closure;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use Throwable;

/** The guarded capabilities an orchestration strategy may use during one agent run. */
interface LLMAgentSession
{
    /** @var int Maximum model turns this run may open. */
    public int $maxIterations {
        get;
    }
    /** @var bool Whether this run may advertise and launch a subagent. */
    public bool $canSpawnSubagents {
        get;
    }
    /** @var string|null The system prompt inherited by this run. */
    public ?string $systemPrompt {
        get;
    }
    /** @var ArrayClass<LLMMessage> A disposable snapshot of the complete history accumulated by this run. */
    public ArrayClass $messages {
        get;
    }
    /** @var ArrayClass<ToolDescriptor> The real provider-neutral tools available through the protected executor. */
    public ArrayClass $tools {
        get;
    }

    /**
     * Performs one guarded provider turn while optionally advertising strategy-owned synthetic tools in addition to the real catalogue.
     *
     * @param ArrayClass<ToolDescriptor>|null $syntheticTools Additional descriptors handled by the strategy, or `null` for none.
     * @return LLMTurn The normalized provider turn.
     * @throws Throwable An unexpected context or provider fault.
     */
    public function completeTurn(?ArrayClass $syntheticTools = null): LLMTurn;

    /**
     * Executes one real tool through approval, budget, deadline, cache and trace policy.
     *
     * @param LLMToolCall $call The invocation requested by a model turn.
     * @return LLMToolExecutionResult The result already appended to the run history.
     * @throws Throwable An unexpected tool fault.
     */
    public function executeTool(LLMToolCall $call): LLMToolExecutionResult;

    /**
     * Records a successful result for a strategy-owned synthetic tool without executing application code.
     *
     * @param LLMToolCall $call The synthetic invocation requested by the model.
     * @param string $message The result the strategy should feed back to the model.
     * @return LLMToolExecutionResult The successful result already appended to the run history.
     * @throws Throwable An unexpected trace fault.
     */
    public function completeSyntheticToolCall(LLMToolCall $call, string $message): LLMToolExecutionResult;

    /**
     * Records a correctable rejection for any pending tool call without executing application code.
     *
     * A strategy may use this to enforce an orchestration phase, such as requiring a plan before
     * real tools execute. The runtime still verifies the exact model call, consumes the shared
     * tool-call budget, appends the result, and closes its trace span.
     *
     * @param LLMToolCall $call The invocation the strategy refuses to execute in its current phase.
     * @param string $message The correction the model should receive.
     * @return LLMToolExecutionResult The failed result already appended to the run history.
     * @throws Throwable An unexpected trace fault.
     */
    public function rejectToolCall(LLMToolCall $call, string $message): LLMToolExecutionResult;

    /**
     * Records a correctable validation failure for a strategy-owned synthetic tool without executing application code.
     *
     * @param LLMToolCall $call The invalid synthetic invocation.
     * @param string $message The correction the model should receive.
     * @return LLMToolExecutionResult The failed result already appended to the run history.
     * @throws Throwable An unexpected trace fault.
     */
    public function rejectSyntheticToolCall(LLMToolCall $call, string $message): LLMToolExecutionResult;

    /**
     * Launches one guarded child run and maps its terminal result into the parent tool result.
     *
     * @param LLMToolCall $call The synthetic invocation requesting delegation.
     * @param LLMAgentRunRequest $request The isolated child request.
     * @param LLMAgentLoop $loop The strategy the child should execute.
     * @param Closure(LLMRun): LLMAgentToolResult $transform Maps the child run into model-facing tool content.
     * @return LLMToolExecutionResult The mapped result already appended to the parent history.
     * @throws Throwable An unexpected child or trace fault.
     */
    public function executeSubagent(LLMToolCall $call, LLMAgentRunRequest $request, LLMAgentLoop $loop, Closure $transform): LLMToolExecutionResult;
}
