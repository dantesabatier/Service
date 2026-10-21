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
     * @param array<array-key, mixed>|null $structuredContent The same answer as data for a UI to render, or null when the tool contributed none or the session cannot carry it.
     */
    public function __construct(public readonly ArrayClass $content, public readonly bool $isError = false, public readonly ?array $structuredContent = null)
    {
    }

    /**
     * Serializes the result into the MCP `tools/call` shape, adding `isError` only when the call failed and
     * `structuredContent` only when there is a payload to carry.
     *
     * @return array{content: ArrayClass<ContentItem>, isError?: true, structuredContent?: array<array-key, mixed>} The MCP result payload.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $data = ["content" => $this->content];
        if ($this->isError) {
            $data["isError"] = true;
        }
        if ($this->structuredContent !== null) {
            $data["structuredContent"] = $this->structuredContent;
        }
        return $data;
    }
}
