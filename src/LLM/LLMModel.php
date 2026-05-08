<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\Dictionary;

/** Describes an available model within a provider, with a human-readable name, the API identifier sent in requests, and a capability tier (e.g. "opus", "sonnet", "haiku"). */
final class LLMModel
{
    public Dictionary $dictionaryRepresentation {
        get => new Dictionary(["name" => $this->name, "identifier" => $this->identifier, "tier" => $this->tier]);
    }

    public function __construct(public readonly string $name, public readonly string $identifier, public readonly string $tier)
    {
    }
}
