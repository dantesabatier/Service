<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** The provider-neutral text produced by an executed tool call, and the same answer as data when the tool contributed it. */
final readonly class LLMToolExecutionResult
{
    /**
     * @param string $text The content to feed back to the model.
     * @param bool $isError Whether the content describes a correctable failure rather than a successful result.
     * @param array<array-key, mixed>|null $structuredContent The same answer as data for a UI to render. The model never reads it.
     */
    public function __construct(public string $text, public bool $isError = false, public ?array $structuredContent = null)
    {
    }
}
