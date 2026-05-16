<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Schema;

use BackedEnum;
use JsonSerializable;
use Override;
use Sabatier\Foundation\Dictionary;

/**
 * Represents an enum type in the model schema returned by describe_model.
 */
final readonly class EnumSchema implements JsonSerializable
{
    /**
     * @param class-string<BackedEnum> $className
     * @param Dictionary<string|int> $cases Map of case name → backed value (int or string). Always use the value, never the name.
     */
    public function __construct(public string $className, public Dictionary $cases)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["enumClass" => $this->className, "cases" => $this->cases];
    }
}
