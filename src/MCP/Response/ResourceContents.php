<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Response;

use JsonSerializable;
use Override;

/** The contents of one resource, as answered by `resources/read`. */
final readonly class ResourceContents implements JsonSerializable
{
    /**
     * @param string $uri The identifier the contents were read by.
     * @param string $mimeType The media type of the text.
     * @param string $text The resource's text; for an MCP App, the markup of its user interface.
     * @param array<array-key, mixed>|null $meta The resource's `_meta`, carrying the `ui` declaration a host reads before rendering.
     */
    public function __construct(public string $uri, public string $mimeType, public string $text, public ?array $meta = null)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        $data = ["uri" => $this->uri, "mimeType" => $this->mimeType, "text" => $this->text];
        if ($this->meta !== null) {
            $data["_meta"] = $this->meta;
        }
        return $data;
    }
}
