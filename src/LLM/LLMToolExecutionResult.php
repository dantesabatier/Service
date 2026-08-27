<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** The provider-neutral text produced by an executed tool call. */
final readonly class LLMToolExecutionResult
{
    /**
     * @param string $text The content to feed back to the model.
     * @param bool $isError Whether the content describes a correctable failure rather than a successful result.
     */
    public function __construct(public string $text, public bool $isError = false)
    {
    }
}
