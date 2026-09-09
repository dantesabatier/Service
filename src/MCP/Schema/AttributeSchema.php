<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Schema;

use JsonSerializable;
use Override;

/**
 * Describes an attribute on an entity in the model schema returned by describe_model.
 */
final readonly class AttributeSchema implements JsonSerializable
{
    public function __construct(public string $name, public string $type, public bool $nullable, public ?string $label = null, public array $aliases = [], public ?EnumSchema $enum = null, public bool $transient = false)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        $data = ["type" => $this->type, "nullable" => $this->nullable];
        // Declared only when true: a transient attribute cannot be used in a predicate or a sort descriptor against a SQL store, and the client has no other way to tell one apart. Persisted attributes are the majority, so marking only the exception keeps the schema short.
        if ($this->transient) {
            $data["transient"] = true;
        }
        if ($this->label !== null) {
            $data["es"] = $this->label;
        }
        if ($this->aliases !== []) {
            $data["aliases"] = $this->aliases;
        }
        if ($this->enum !== null) {
            $data["enumClass"] = $this->enum->className;
            $data["cases"] = $this->enum->cases;
        }
        return $data;
    }
}
