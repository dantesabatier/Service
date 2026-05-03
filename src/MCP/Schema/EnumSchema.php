<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Schema;

use BackedEnum;
use JsonSerializable;
use Override;

final readonly class EnumSchema implements JsonSerializable
{
    /**
     * @param class-string<BackedEnum> $className
     * @param array<string, string|int> $cases
     */
    public function __construct(public string $className, public array $cases)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["enumClass" => $this->className, "cases" => $this->cases];
    }
}
