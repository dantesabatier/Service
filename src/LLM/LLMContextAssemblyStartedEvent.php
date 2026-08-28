<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** Opens the context-assembly span for one model iteration. */
final readonly class LLMContextAssemblyStartedEvent implements LLMRunEvent
{
    /**
     * @param LLMRunContext $context The run that owns the assembly.
     * @param float $timestamp The Unix time at which assembly started.
     * @param int $iteration The model iteration being prepared, counting from one.
     * @param int $messageCount Messages available before assembly.
     */
    public function __construct(public LLMRunContext $context, public float $timestamp, public int $iteration, public int $messageCount)
    {
    }
}
