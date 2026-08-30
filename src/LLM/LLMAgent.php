<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Throwable;

/** Configures an agent and delegates each run to a replaceable orchestration strategy. */
final readonly class LLMAgent
{
    private LLMAgentEnvironment $environment;
    private LLMAgentLoop $loop;

    /**
     * @param LLMClient $client The provider client every model turn is sent through.
     * @param ToolRegistry|LLMToolExecutor $toolRegistry The in-process registry to adapt, or a replaceable executor that supplies and runs the model's real tools.
     * @param int $maxIterations Turns the root loop may take before it gives up on the model concluding.
     * @param bool $canSpawnSubagents Whether the selected loop may offer its delegation mechanism.
     * @param float|null $timeLimit Seconds the whole run may take, or `null` for no limit.
     * @param LLMExecutionPolicy|null $executionPolicy Hard limits and write approvals shared with every subagent. Null uses the secure default policy.
     * @param LLMContextAssembler|null $contextAssembler Formats and bounds the history sent on every model turn. Null preserves the complete history.
     * @param LLMRunObserver|null $observer Receives structured loop events and is inherited by every subagent. Null reports nothing.
     * @param LLMRunObserverFailurePolicy $observerFailurePolicy Whether an observer failure is logged and ignored or aborts the run.
     * @param LLMClock|null $clock Supplies event timestamps, durations and deadlines. Null uses the system clock.
     * @param LLMAgentLoop|null $loop The orchestration strategy, or `null` for the built-in ReAct loop.
     */
    public function __construct(LLMClient $client, ToolRegistry|LLMToolExecutor $toolRegistry, int $maxIterations = 25, bool $canSpawnSubagents = true, ?float $timeLimit = null, ?LLMExecutionPolicy $executionPolicy = null, ?LLMContextAssembler $contextAssembler = null, ?LLMRunObserver $observer = null, LLMRunObserverFailurePolicy $observerFailurePolicy = LLMRunObserverFailurePolicy::bestEffort, ?LLMClock $clock = null, ?LLMAgentLoop $loop = null)
    {
        $toolExecutor = $toolRegistry instanceof ToolRegistry ? new InProcessLLMToolExecutor($toolRegistry) : $toolRegistry;
        $this->environment = new LLMAgentEnvironment($client, $toolExecutor, $maxIterations, $canSpawnSubagents, $timeLimit, $executionPolicy ?? new LLMExecutionPolicy(), $contextAssembler ?? new WindowedLLMContextAssembler(), $observer, $observerFailurePolicy, $clock ?? new SystemLLMClock());
        $this->loop = $loop ?? new ReActLLMAgentLoop();
    }

    /**
     * Runs the configured strategy and returns only the messages generated during this invocation.
     *
     * @param ArrayClass<LLMMessage> $messages The input conversation history; cloned before the strategy receives it.
     * @param string|null $systemPrompt The system prompt to send on every model turn, or `null` for none.
     * @return LLMRun The generated messages, provider token usage and normalized stopping reason.
     * @throws Throwable A fatal program fault raised by the loop or one of its dependencies.
     */
    public function run(ArrayClass $messages, ?string $systemPrompt = null): LLMRun
    {
        $request = new LLMAgentRunRequest(clone $messages, $systemPrompt);
        return new LLMAgentRuntime($request, $this->environment)->execute($this->loop);
    }
}
