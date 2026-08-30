<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** Retains the provider-neutral services and limits from which protected agent runtimes are created. */
final readonly class LLMAgentEnvironment
{
    /**
     * @param LLMClient $client The provider client available for model turns.
     * @param LLMToolExecutor $toolExecutor The catalogue and execution boundary for real tools.
     * @param int $maxIterations The maximum model turns one root run may take.
     * @param bool $canSpawnSubagents Whether a strategy may offer its delegation mechanism.
     * @param float|null $timeLimit The root run's wall-clock limit in seconds, or `null` for none.
     * @param LLMExecutionPolicy $executionPolicy The shared approval and consumption limits.
     * @param LLMContextAssembler $contextAssembler The final context formatting and bounding strategy.
     * @param LLMRunObserver|null $observer The trace sink, or `null` when observation is disabled.
     * @param LLMRunObserverFailurePolicy $observerFailurePolicy Whether trace delivery failures abort the run.
     * @param LLMClock $clock The source of trace timestamps, durations and deadlines.
     */
    public function __construct(public LLMClient $client, public LLMToolExecutor $toolExecutor, public int $maxIterations, public bool $canSpawnSubagents, public ?float $timeLimit, public LLMExecutionPolicy $executionPolicy, public LLMContextAssembler $contextAssembler, public ?LLMRunObserver $observer, public LLMRunObserverFailurePolicy $observerFailurePolicy, public LLMClock $clock)
    {
    }
}
