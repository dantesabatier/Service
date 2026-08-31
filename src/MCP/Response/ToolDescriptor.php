<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Response;

use JsonSerializable;
use Override;
use stdClass;

/**
 * Describes a tool for MCP discovery and provider-specific LLM formatting; annotations are advisory, not execution policy.
 */
final readonly class ToolDescriptor implements JsonSerializable
{
    /**
     * @param string $name The stable tool name used for dispatch.
     * @param string $description The description presented to the model.
     * @param array<array-key, mixed> $inputSchema The JSON Schema for the tool's arguments.
     * @param string|null $title An optional human-readable display name.
     * @param array<string, mixed>|null $annotations Optional MCP hints, retained independently of trusted execution classifiers.
     */
    public function __construct(public string $name, public string $description, public array $inputSchema, public ?string $title = null, public ?array $annotations = null)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        $data = ["name" => $this->name, "description" => $this->description, "inputSchema" => $this->inputSchema];
        if ($this->title !== null) {
            $data["title"] = $this->title;
        }
        if ($this->annotations !== null) {
            $data["annotations"] = $this->annotations === [] ? new stdClass() : $this->annotations;
        }
        return $data;
    }
}
