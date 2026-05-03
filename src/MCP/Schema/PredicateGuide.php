<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Schema;

use JsonSerializable;
use Override;

/**
 * Help text and examples for predicate syntax in the model schema.
 */
final readonly class PredicateGuide implements JsonSerializable
{
    /**
     * @param array<string, string> $placeholders
     * @param list<string> $operators
     * @param list<string> $examples
     */
    public function __construct(public array $placeholders, public array $operators, public array $examples)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["placeholders" => $this->placeholders, "operators" => $this->operators, "examples" => $this->examples];
    }
}
