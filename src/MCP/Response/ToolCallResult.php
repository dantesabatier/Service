<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Response;

use JsonSerializable;
use Override;
use Sabatier\Foundation\ArrayClass;

/** The MCP wire result of one tool call, usable by both the server handler and a remote client. */
final class ToolCallResult implements JsonSerializable
{
    /** @var string The text content joined for an agent that consumes a single tool-result string. */
    public string $text {
        get => $this->content->map(fn(ContentItem $item): string => $item->text)->join("\n");
    }

    /**
     * @param ArrayClass<ContentItem> $content The content items returned by the tool.
     * @param bool $isError Whether the call failed; serialized as the MCP `isError` flag so the model is told.
     */
    public function __construct(public readonly ArrayClass $content, public readonly bool $isError = false)
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
