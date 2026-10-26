<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** A tool call rejoined with the result that came back for it, carrying the failure flag the result message was marked with. A result of `null` means the call was made and never answered. */
final readonly class LLMToolCallResult
{
    /**
     * @param LLMToolCall $call The invocation the model requested.
     * @param string|null $content The text the tool returned, or `null` when the run ended before the call was answered.
     * @param bool $isError Whether the call failed. A failed call still carries its content: the failure message is what the model was told.
     * @param array<array-key, mixed>|null $structuredContent The tool's answer as data for a UI to render, or `null` when the tool contributed none — as with a call answered from the run's cache, which repeats no result.
     */
    public function __construct(public LLMToolCall $call, public ?string $content = null, public bool $isError = false, public ?array $structuredContent = null)
    {
    }
}
