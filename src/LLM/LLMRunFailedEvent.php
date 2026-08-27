<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** Closes a run that escaped through an unexpected exception instead of returning LLMRun. */
final readonly class LLMRunFailedEvent implements LLMRunEvent
{
    /**
     * @param LLMRunContext $context The identity and parentage of the run.
     * @param float $timestamp The Unix time at which the run failed.
     * @param string $errorType The exception class.
     * @param string $errorMessage The exception message.
     * @param int $inputTokens Provider-reported input tokens accumulated before failure.
     * @param int $outputTokens Provider-reported output tokens accumulated before failure.
     * @param float $duration Seconds elapsed before failure.
     */
    public function __construct(public LLMRunContext $context, public float $timestamp, public string $errorType, public string $errorMessage, public int $inputTokens, public int $outputTokens, public float $duration)
    {
    }
}
