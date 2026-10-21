<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Response;

use JsonSerializable;
use Override;

/** @internal */
final readonly class ResourcesCapability implements JsonSerializable
{
    public function __construct(public bool $listChanged = false, public bool $subscribe = false)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["listChanged" => $this->listChanged, "subscribe" => $this->subscribe];
    }
}
