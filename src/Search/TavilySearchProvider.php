<?php

declare(strict_types=1);

namespace Sabatier\Service\Search;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\fatal_error;

/**
 * WebSearchProvider for the Tavily API.
 *
 * The Tavily key comes from the environment (`WEB_SEARCH_API_KEY`) and is passed by
 * {@see WebSearchProvider::provider()}; a provider without one fails the call with a correctable
 * message telling the model the tool is not configured. The provider-specific wire format
 * (endpoint, body fields, result shape) lives here and nowhere else.
 */
final class TavilySearchProvider extends WebSearchProvider
{
    private const string defaultEndpoint = "https://api.tavily.com/search";
    private const int resultContentLimit = 800;

    #[Override]
    public string $name = "Tavily";
    #[Override]
    public string $identifier = "tavily";
    #[Override]
    public URL $endpoint {
        get => new URL(self::defaultEndpoint);
    }

    #[Override]
    protected function buildRequest(string $query, int $maxResults): URLRequest
    {
        $request = new URLRequest($this->endpoint);
        $request->httpMethod = HTTPRequestMethod::post;
        $request->setValueForHttpHeaderField("application/json", "Content-Type");
        $request->httpBody = (string)json_encode([
            "api_key" => $this->key ?? fatal_error("web_search is not configured on this server: the WEB_SEARCH_API_KEY environment variable is not set. Tell the user the server administrator must set it to a Tavily API key."),
            "query" => $query,
            "max_results" => $maxResults,
            "include_answer" => true,
            "search_depth" => "basic",
        ]);
        return $request;
    }

    #[Override]
    protected function parse(Dictionary $body): WebSearchResult
    {
        /** @var ArrayClass<Dictionary<mixed>> $rawResults */
        $rawResults = $body["results"] ?? fatal_error("Web search failed: the provider returned no results payload (invalid key, HTTP error or malformed response). Do not invent results; tell the user web search is temporarily unavailable and suggest retrying.");
        $results = $rawResults->map(fn(Dictionary $result): array => [
            "title" => (string)($result["title"] ?? ""),
            "url" => (string)($result["url"] ?? ""),
            "content" => mb_strimwidth((string)($result["content"] ?? ""), 0, self::resultContentLimit, "…"),
        ])->array;
        return new WebSearchResult((string)($body["query"] ?? ""), $body["answer"] ?? null, $results);
    }
}
