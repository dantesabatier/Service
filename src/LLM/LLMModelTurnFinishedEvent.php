<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** Records one successful provider turn before its requested tools are handled. */
final readonly class LLMModelTurnFinishedEvent implements LLMRunEvent
{
    /**
     * @param LLMRunContext $context The run that owns the turn.
     * @param float $timestamp The Unix time at which the turn finished.
     * @param int $iteration The turn number, counting from one.
     * @param int $inputTokens Provider-reported input tokens.
     * @param int $outputTokens Provider-reported output tokens.
     * @param LLMTurnStopReason $stopReason Why the provider ended the turn.
     * @param int $toolCallCount Tool calls requested by the turn.
     * @param float $duration Seconds spent waiting for the provider.
     */
    public function __construct(public LLMRunContext $context, public float $timestamp, public int $iteration, public int $inputTokens, public int $outputTokens, public LLMTurnStopReason $stopReason, public int $toolCallCount, public float $duration)
    {
    }
}
