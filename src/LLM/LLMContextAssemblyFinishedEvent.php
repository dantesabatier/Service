<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** Closes a successful context-assembly span before the provider is called. */
final readonly class LLMContextAssemblyFinishedEvent implements LLMRunEvent
{
    /**
     * @param LLMRunContext $context The run that owns the assembly.
     * @param float $timestamp The Unix time at which assembly finished.
     * @param int $iteration The model iteration being prepared, counting from one.
     * @param int $messageCount Messages retained in the assembled context.
     * @param int $omittedMessageCount Messages omitted from the available history.
     * @param bool $wasCompacted Whether omitted history was replaced with a summary.
     * @param bool $isWithinLimit Whether the assembled context satisfies its configured limits.
     * @param float $duration Seconds spent assembling context, including retrieval.
     */
    public function __construct(public LLMRunContext $context, public float $timestamp, public int $iteration, public int $messageCount, public int $omittedMessageCount, public bool $wasCompacted, public bool $isWithinLimit, public float $duration)
    {
    }
}
