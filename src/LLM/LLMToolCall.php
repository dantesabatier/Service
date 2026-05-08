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
     */
    public function __construct(public string $id, public string $name, public Dictionary $arguments)
    {
    }
}
