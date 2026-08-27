<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** Marks a root or subagent run before it begins its first turn. */
final readonly class LLMRunStartedEvent implements LLMRunEvent
{
    /**
     * @param LLMRunContext $context The identity and parentage of the run.
     * @param float $timestamp The Unix time at which the run started.
     * @param int $messageCount Messages supplied by the caller.
     * @param bool $hasSystemPrompt Whether the run carries a system prompt.
     */
    public function __construct(public LLMRunContext $context, public float $timestamp, public int $messageCount, public bool $hasSystemPrompt)
    {
    }
}
