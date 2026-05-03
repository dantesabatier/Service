<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Response;

use JsonSerializable;
use Override;

/**
 * Basic server identity information returned during initialization.
 */
final readonly class ServerInfo implements JsonSerializable
{
    public function __construct(public string $name, public string $version)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["name" => $this->name, "version" => $this->version];
    }
}
