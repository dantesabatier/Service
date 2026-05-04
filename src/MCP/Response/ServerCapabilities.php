<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Response;

use JsonSerializable;
use Override;

/** @internal */
final readonly class ServerCapabilities implements JsonSerializable
{
    public function __construct(public ToolsCapability $tools)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["tools" => $this->tools];
    }
}
