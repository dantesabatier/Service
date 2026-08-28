<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** Closes a context-assembly span when the assembler throws instead of returning context. */
final readonly class LLMContextAssemblyFailedEvent implements LLMRunEvent
{
    /**
     * @param LLMRunContext $context The run that owns the assembly.
     * @param float $timestamp The Unix time at which assembly failed.
     * @param int $iteration The model iteration being prepared, counting from one.
     * @param string $errorType The exception class.
     * @param string $errorMessage The exception message.
     * @param float $duration Seconds spent in context assembly.
     */
    public function __construct(public LLMRunContext $context, public float $timestamp, public int $iteration, public string $errorType, public string $errorMessage, public float $duration)
    {
    }
}
