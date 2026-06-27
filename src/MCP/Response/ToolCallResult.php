<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Response;

use JsonSerializable;
use Override;
use Sabatier\Foundation\ArrayClass;

/** @internal */
final readonly class ToolCallResult implements JsonSerializable
{
    /**
     * @param ArrayClass<ContentItem> $content The content items returned by the tool.
     * @param bool $isError Whether the call failed; serialized as the MCP `isError` flag so the model is told.
     */
    public function __construct(public ArrayClass $content, public bool $isError = false)
    {
    }

    /**
     * Serializes the result into the MCP `tools/call` shape, adding `isError` only when the call failed.
     *
     * @return array{content: ArrayClass<ContentItem>, isError?: true} The MCP result payload.
     */
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
