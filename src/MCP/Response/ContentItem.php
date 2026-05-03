<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Response;

use JsonSerializable;
use Override;

/**
 * Single content item returned by a tool (typed text content).
 */
final readonly class ContentItem implements JsonSerializable
{
    public function __construct(public string $type, public string $text)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["type" => $this->type, "text" => $this->text];
    }
}
