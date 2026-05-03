<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Schema;

use JsonSerializable;
use Override;

/**
 * Describes a relationship on an entity in the model schema returned by describe_model.
 */
final readonly class RelationshipSchema implements JsonSerializable
{
    public function __construct(public string $name, public string $target, public bool $toMany, public bool $nullable)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["target" => $this->target, "toMany" => $this->toMany, "nullable" => $this->nullable];
    }
}
