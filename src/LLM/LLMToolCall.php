<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\Dictionary;

/** A single tool invocation requested by the model, identified by a provider-assigned id and carrying the parsed arguments. */
final readonly class LLMToolCall
{
    /**
     * @param string $id
     * @param string $name
     * @param Dictionary<mixed> $arguments
     * @param Dictionary<mixed>|null $extraContent Provider data attached to this call that must be echoed back verbatim on the next turn, such as Gemini's `extra_content.google.thought_signature`; `null` when the provider attached none.
     */
    public function __construct(public string $id, public string $name, public Dictionary $arguments, public ?Dictionary $extraContent = null)
    {
    }
}
