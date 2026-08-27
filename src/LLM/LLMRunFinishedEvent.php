<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** Closes a run that returned an LLMRun, whether complete or stopped by policy. */
final readonly class LLMRunFinishedEvent implements LLMRunEvent
{
    /**
     * @param LLMRunContext $context The identity and parentage of the run.
     * @param float $timestamp The Unix time at which the run finished.
     * @param LLMRunStopReason $stopReason Why the loop stopped.
     * @param bool $isComplete Whether the run produced a final answer.
     * @param bool|null $isRetryable Whether repeating an incomplete run may succeed.
     * @param int $messageCount Messages the run generated.
     * @param int $inputTokens Provider-reported input tokens across the run, including completed subagents.
     * @param int $outputTokens Provider-reported output tokens across the run, including completed subagents.
     * @param float $duration Seconds elapsed across the run.
     */
    public function __construct(public LLMRunContext $context, public float $timestamp, public LLMRunStopReason $stopReason, public bool $isComplete, public ?bool $isRetryable, public int $messageCount, public int $inputTokens, public int $outputTokens, public float $duration)
    {
    }
}
