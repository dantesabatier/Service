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
    public function __construct(public ArrayClass $content, public bool $isError = false)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        $data = ["content" => $this->content];
        if ($this->isError) {
            $data["isError"] = true;
        }
        return $data;
    }
}
