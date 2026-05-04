<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Response;

use JsonSerializable;
use Override;
use Sabatier\Foundation\ArrayClass;

/** @internal */
final readonly class ToolCallResult implements JsonSerializable
{
    /** @param ArrayClass<ContentItem> $content */
    public function __construct(public ArrayClass $content)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["content" => $this->content];
    }
}
