<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Response;

use JsonSerializable;
use Override;

/** Describes a resource for MCP discovery; the host reads its contents separately with `resources/read`. */
final readonly class ResourceDescriptor implements JsonSerializable
{
    /**
     * @param string $uri The stable identifier the host reads the resource by.
     * @param string $name The programmatic name of the resource.
     * @param string $mimeType The media type of the contents the resource answers with.
     * @param string|null $title An optional human-readable display name.
     * @param string|null $description An optional explanation of what the resource is for.
     */
    public function __construct(public string $uri, public string $name, public string $mimeType, public ?string $title = null, public ?string $description = null)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        $data = ["uri" => $this->uri, "name" => $this->name, "mimeType" => $this->mimeType];
        if ($this->title !== null) {
            $data["title"] = $this->title;
        }
        if ($this->description !== null) {
            $data["description"] = $this->description;
        }
        return $data;
    }
}
