<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use JsonException;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\Search\WebSearchProvider;
use function Sabatier\Foundation\fatal_error;

/**
 * Generic `web_search` tool whose backing provider is chosen by the application.
 *
 * The tool keeps the MCP surface — name, input schema and argument validation — while
 * deferring the actual search to a {@see WebSearchProvider} resolved by identifier from the
 * environment (see {@see WebSearchProvider::provider()}), the same way `LLMAgent` defers to an
 * application-provided `LLMClient`. Applications configure which provider backs `web_search`
 * through `.env` rather than by subclassing or placing a tool in `MCPTools`.
 * Reads no entities, so it enforces no row- or column-level security; the provider makes
 * an outbound network call on behalf of the authenticated MCP caller.
 */
final class WebSearchTool extends AbstractTool
{
    private const int maxResultsLimit = 10;

    #[Override]
    public string $name {
        get => "web_search";
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => [
                "query" => ["type" => "string", "description" => "The web search query, in natural language."],
                "maxResults" => ["type" => "integer", "description" => "Maximum number of results to return (1-10, default 5).", "minimum" => 1, "maximum" => self::maxResultsLimit],
            ],
            "required" => ["query"],
        ];
    }
    /** @var WebSearchProvider The search provider backing this tool, resolved by identifier from the environment. */
    public WebSearchProvider $provider {
        get => $this->provider ??= WebSearchProvider::provider();
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws JsonException
     */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        $query = trim((string)$arguments["query"]);
        if ($query === "") {
            fatal_error("web_search requires a non-empty query.");
        }
        $maxResults = min(max((int)($arguments["maxResults"] ?? 5), 1), self::maxResultsLimit);
        $result = $this->provider->search($query, $maxResults);
        return $this->jsonResult([
            "query" => $result->query !== "" ? $result->query : $query,
            "answer" => $result->answer,
            "results" => $result->results,
        ]);
    }
}
