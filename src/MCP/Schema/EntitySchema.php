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
    public function __construct(public string $name, public string $className, public string $label, public array $aliases, public Dictionary $attributes, public Dictionary $relationships, public bool $abstract = false)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        $data = ["class" => $this->className, "es" => $this->label, "aliases" => $this->aliases];
        // Solo se declara cuando lo es: una entidad abstracta no tiene tabla y no
        // se puede instanciar, pero un fetch sobre ella sí alcanza las filas de
        // todas sus sub-entidades concretas. Sin esto el cliente no distingue una
        // de otra hasta que el intento de creación falla.
        if ($this->abstract) {
            $data["abstract"] = true;
        }
        return [...$data, "attributes" => $this->attributes, "relationships" => $this->relationships];
    }
}
