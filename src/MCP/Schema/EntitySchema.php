<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Schema;

use JsonSerializable;
use Override;
use Sabatier\Foundation\Dictionary;

/**
 * Describes an entity in the model schema returned by describe_model.
 */
final readonly class EntitySchema implements JsonSerializable
{
    /**
     * @param string $name
     * @param string $className
     * @param string $label
     * @param list<string> $aliases
     * @param Dictionary<AttributeSchema> $attributes
     * @param Dictionary<RelationshipSchema> $relationships
     */
    public function __construct(public string $name, public string $className, public string $label, public array $aliases, public Dictionary $attributes, public Dictionary $relationships)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["class" => $this->className, "es" => $this->label, "aliases" => $this->aliases, "attributes" => $this->attributes, "relationships" => $this->relationships];
    }
}
