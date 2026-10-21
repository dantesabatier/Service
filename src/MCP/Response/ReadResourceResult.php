<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Response;

use JsonSerializable;
use Override;
use Sabatier\Foundation\ArrayClass;

/** @internal */
final readonly class ReadResourceResult implements JsonSerializable
{
    /** @param ArrayClass<ResourceContents> $contents */
    public function __construct(public ArrayClass $contents)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["contents" => $this->contents];
    }
}
