<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** Closes a model-turn span when the provider client throws instead of returning a turn. */
final readonly class LLMModelTurnFailedEvent implements LLMRunEvent
{
    /**
     * @param LLMRunContext $context The run that owns the turn.
     * @param float $timestamp The Unix time at which the turn failed.
     * @param int $iteration The turn number, counting from one.
     * @param string $errorType The exception class.
     * @param string $errorMessage The exception message.
     * @param float $duration Seconds spent in the provider call.
     */
    public function __construct(public LLMRunContext $context, public float $timestamp, public int $iteration, public string $errorType, public string $errorMessage, public float $duration)
    {
    }
}
