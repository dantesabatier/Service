<?php

declare(strict_types=1);

namespace Sabatier\Service\Search;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\Networking\URLResponse;
use Sabatier\Foundation\Networking\URLSession;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\Service\WebSearchApiKey;
use const Sabatier\Service\WebSearchProviderDefault;
use const Sabatier\Service\WebSearchProviderKey;

/**
 * Abstract base for web search integrations, the search-side counterpart of `LLMClient`.
 *
 * Handles transport via URLSession (`send`). Subclasses own request serialization
 * (`buildRequest`) and response deserialization (`parse`), covering provider-specific wire
 * formats. The public surface is `search`, which executes one request–response cycle and
 * returns a `WebSearchResult`. A provider carries its own `key` and `endpoint`, so which
 * provider a server uses is an application decision — the same way the application decides
 * which `LLMClient` backs `LLMAgent` rather than the framework.
 */
abstract class WebSearchProvider
{
    /** @var string Human-readable provider name for user-facing display, e.g. "Tavily". */
    abstract public string $name {
        get;
    }
    /** @var string Opaque identifier used to select this provider via `WEB_SEARCH_PROVIDER`; not for user-facing display. */
    abstract public string $identifier {
        get;
    }
    /** @var URL The provider API endpoint this instance talks to; owned by the concrete provider, not the caller. */
    abstract public URL $endpoint {
        get;
    }

    /**
     * @param string|null $key The provider API key, or `null` when unconfigured; subclasses fail the call with a correctable message telling the model the tool is not configured.
     */
    public function __construct(public readonly ?string $key = null)
    {
    }

    /**
     * Resolves the `WebSearchProvider` the application configured in its environment.
     *
     * The application picks the backing provider by setting `WEB_SEARCH_PROVIDER` in its
     * `.env` (e.g. `tavily`), the same way it picks an `LLMClient` by provider identifier;
     * the provider reads its own configuration — the API key from `WEB_SEARCH_API_KEY` —
     * from the same source. `web_search` stays a framework tool; applications never subclass it.
     */
    public static function provider(): WebSearchProvider
    {
        $environment = ProcessInfo::processInfo()->environment;
        $identifier = (string)($environment[WebSearchProviderKey] ?? WebSearchProviderDefault);
        $key = (string)($environment[WebSearchApiKey] ?? "");
        return match ($identifier) {
            "tavily" => new TavilySearchProvider($key !== "" ? $key : null),
            default => fatal_error("Unknown web search provider identifier \"$identifier\". Supported identifiers: tavily."),
        };
    }

    /**
     * @throws InternalInconsistencyException
     */
    abstract protected function buildRequest(string $query, int $maxResults): URLRequest;

    /**
     * @throws InternalInconsistencyException
     */
    abstract protected function parse(Dictionary $body): WebSearchResult;

    /**
     * Runs one search and returns the normalized result.
     *
     * @throws InternalInconsistencyException
     */
    public function search(string $query, int $maxResults): WebSearchResult
    {
        return $this->parse($this->send($this->buildRequest($query, $maxResults)));
    }

    /**
     * Sends one request and returns the response body as a Dictionary — the same URLSession
     * transport `LLMClient::send()` uses.
     *
     * @throws InternalInconsistencyException
     */
    protected function send(URLRequest $request): Dictionary
    {
        $data = null;
        $error = null;
        URLSession::shared()->dataTaskWithRequest($request, function (?string $responseData, ?URLResponse $urlResponse, ?Error $err) use (&$data, &$error): void {
            $data = $responseData;
            $error = $err;
        })->resume();
        !$error instanceof Error ?: throw new InternalInconsistencyException(error: $error);
        return Dictionary::dictionaryWithArray(json_decode($data ?? "[]", true) ?? [], false);
    }
}
