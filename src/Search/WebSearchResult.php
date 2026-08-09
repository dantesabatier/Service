<?php

declare(strict_types=1);

namespace Sabatier\Service\Search;

/** Normalized, provider-agnostic outcome of a web search. */
final readonly class WebSearchResult
{
    /**
     * @param string $query The search query the result answers.
     * @param string|null $answer A provider-generated summary answer, when available.
     * @param list<array{title: string, url: string, content: string}> $results Bounded result items: title, URL, and a truncated content snippet, so the token cost of the tool result stays sane.
     */
    public function __construct(public string $query, public ?string $answer, public array $results)
    {
    }
}
