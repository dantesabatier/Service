<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** Marks a tool call before approval, budget and execution are resolved. */
final readonly class LLMToolCallStartedEvent implements LLMRunEvent
{
    /**
     * @param LLMRunContext $context The run that owns the call.
     * @param float $timestamp The Unix time at which handling started.
     * @param int $iteration The model turn that requested the call.
     * @param string $callIdentifier The provider or framework call identifier.
     * @param string $toolName The requested tool.
     */
    public function __construct(public LLMRunContext $context, public float $timestamp, public int $iteration, public string $callIdentifier, public string $toolName)
    {
    }
}
