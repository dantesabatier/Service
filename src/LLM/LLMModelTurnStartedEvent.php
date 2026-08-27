<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** Describes the bounded context immediately before a model turn is sent. */
final readonly class LLMModelTurnStartedEvent implements LLMRunEvent
{
    /**
     * @param LLMRunContext $context The run that owns the turn.
     * @param float $timestamp The Unix time at which the turn started.
     * @param int $iteration The turn number, counting from one.
     * @param int $messageCount Messages sent after context assembly.
     * @param int $omittedMessageCount Messages omitted by context assembly.
     * @param bool $wasCompacted Whether omitted history was replaced with a summary.
     */
    public function __construct(public LLMRunContext $context, public float $timestamp, public int $iteration, public int $messageCount, public int $omittedMessageCount, public bool $wasCompacted)
    {
    }
}
