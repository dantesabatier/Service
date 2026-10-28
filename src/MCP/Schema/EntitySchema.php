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
     * @param bool $abstract
     */
    public function __construct(public string $name, public string $className, public string $label, public array $aliases, public Dictionary $attributes, public Dictionary $relationships, public bool $abstract = false)
    {
    }

    /** `abstract` is emitted only when true: an abstract entity cannot be created, yet a fetch on it reaches every concrete sub-entity, and without the flag a client learns that only when the create fails. */
    #[Override]
    public function jsonSerialize(): array
    {
        $data = ["class" => $this->className, "es" => $this->label, "aliases" => $this->aliases];
        if ($this->abstract) {
            $data["abstract"] = true;
        }
        return [...$data, "attributes" => $this->attributes, "relationships" => $this->relationships];
    }
}
