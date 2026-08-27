<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** Records how one requested tool call was resolved without exposing its arguments or result. */
final readonly class LLMToolCallFinishedEvent implements LLMRunEvent
{
    /**
     * @param LLMRunContext $context The run that owns the call.
     * @param float $timestamp The Unix time at which handling finished.
     * @param int $iteration The model turn that requested the call.
     * @param string $callIdentifier The provider or framework call identifier.
     * @param string $toolName The requested tool.
     * @param LLMToolCallDisposition $disposition How the loop resolved the call.
     * @param int $resultSize Bytes in the result supplied to the model, or zero when none was produced.
     * @param float $duration Seconds spent resolving the call, or zero when it was answered from cache.
     */
    public function __construct(public LLMRunContext $context, public float $timestamp, public int $iteration, public string $callIdentifier, public string $toolName, public LLMToolCallDisposition $disposition, public int $resultSize, public float $duration)
    {
    }
}
