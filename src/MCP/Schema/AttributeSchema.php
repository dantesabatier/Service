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
        // Solo se declara cuando lo es: un atributo transitorio no se puede usar
        // en un predicado ni en un sort descriptor contra un store SQL, y el
        // cliente no tiene otra forma de distinguirlo. Los persistidos son la
        // mayoría, así que marcar solo la excepción mantiene el esquema corto.
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
