<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\MCP\Response\ContentItem;

/**
 * The neutral outcome of a single tool invocation, produced by {@see ToolRegistry::call}.
 *
 * `ToolRegistry::call` never lets a tool throw past it: every invocation funnels through one
 * try/catch and comes back as a `ToolResult`. The result carries the tool's content together
 * with whether the call failed, so each caller can translate it into the shape its own audience
 * expects — `ToolsCallHandler` into the MCP envelope, `InProcessLLMToolExecutor` into the
 * provider-neutral text `LLMAgent` feeds back to the model.
 *
 * A failure is a *correctable* error: the LLM mis-called the tool (bad key path, unknown enum,
 * malformed predicate) and `$content` holds the descriptive message it needs to fix the call.
 * Fatal program errors do not become a `ToolResult` — they propagate out of `call` to the caller.
 */
final class ToolResult
{
    /** @var string The content joined into a single string for callers that feed plain text back to the model. */
    public string $text {
        get => $this->content->map(fn(ContentItem $item): string => $item->text)->join("\n");
    }

    /**
     * @param ArrayClass<ContentItem> $content The content items the tool produced, or the failure message wrapped as text.
     * @param bool $isError Whether the call failed with a correctable error the model should be told about.
     */
    private function __construct(public readonly ArrayClass $content, public readonly bool $isError)
    {
    }

    /**
     * Wraps the content of a successful tool invocation.
     *
     * @param ArrayClass<ContentItem> $content The content items the tool produced.
     * @return self A result flagged as successful.
     */
    public static function success(ArrayClass $content): self
    {
        return new self($content, false);
    }

    /**
     * Wraps a correctable failure as text content the model can act on.
     *
     * @param string $message The descriptive error message the model needs to fix its call.
     * @return self A result flagged as an error.
     */
    public static function failure(string $message): self
    {
        return new self(new ArrayClass([new ContentItem("text", $message)]), true);
    }
}
