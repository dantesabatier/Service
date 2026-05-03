<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Schema;

use JsonSerializable;
use Override;

final readonly class AttributeSchema implements JsonSerializable
{
    public function __construct(public string $name, public string $type, public bool $nullable, public ?string $label = null, public array $aliases = [], public ?EnumSchema $enum = null)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        $data = ["type" => $this->type, "nullable" => $this->nullable];
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
