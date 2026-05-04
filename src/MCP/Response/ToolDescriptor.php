<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Response;

use JsonSerializable;
use Override;

/**
 * Describes a single MCP tool (name, description and input schema) passed to the LLM client.
 */
final readonly class ToolDescriptor implements JsonSerializable
{
    public function __construct(public string $name, public string $description, public array $inputSchema)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["name" => $this->name, "description" => $this->description, "inputSchema" => $this->inputSchema];
    }
}
