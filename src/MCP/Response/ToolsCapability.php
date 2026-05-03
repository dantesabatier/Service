<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Response;

use JsonSerializable;
use Override;

/**
 * Describes server capabilities exposed during initialization.
 */
final readonly class ToolsCapability implements JsonSerializable
{
    public function __construct(public bool $listChanged)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["listChanged" => $this->listChanged];
    }
}
