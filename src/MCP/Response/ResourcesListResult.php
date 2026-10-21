<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Response;

use JsonSerializable;
use Override;
use Sabatier\Foundation\ArrayClass;

/** @internal */
final readonly class ResourcesListResult implements JsonSerializable
{
    /** @param ArrayClass<ResourceDescriptor> $resources */
    public function __construct(public ArrayClass $resources)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["resources" => $this->resources];
    }
}
