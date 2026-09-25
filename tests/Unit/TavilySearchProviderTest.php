<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionMethod;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\Search\TavilySearchProvider;
use Sabatier\Service\Search\WebSearchResult;

/**
 * Exercises TavilySearchProvider::parse with fixture bodies — no network involved. A successful
 * Tavily response is normalized into a bounded WebSearchResult, and a body without the `results`
 * payload (HTTP error, invalid key, malformed response) funnels as an InternalInconsistencyException.
 */
final class TavilySearchProviderTest extends TestCase
{
    /** @throws ReflectionException */
    #[Test]
    public function parsesResultsAndTruncatesContent(): void
    {
        $provider = new TavilySearchProvider();
        $body = Dictionary::dictionaryWithArray([
            "query" => "cotton price",
            "answer" => "Cotton trades at 2 USD/lb.",
            "results" => [
                ["title" => "Cotton market", "url" => "https://example.com/cotton", "content" => str_repeat("x", 900)],
            ],
        ]);
        $result = $this->invokeParse($provider, $body);
        $this->assertSame("cotton price", $result->query);
        $this->assertSame("Cotton trades at 2 USD/lb.", $result->answer);
        $this->assertCount(1, $result->results);
        $this->assertSame("Cotton market", $result->results[0]["title"]);
        $this->assertSame("https://example.com/cotton", $result->results[0]["url"]);
        $this->assertLessThanOrEqual(800, mb_strlen($result->results[0]["content"]));
    }

    /** @throws ReflectionException */
    #[Test]
    public function missingResultsPayloadFunnelsAsFailure(): void
    {
        $provider = new TavilySearchProvider();
        $this->expectExceptionMessage("no results payload");
        $this->invokeParse($provider, new Dictionary(["detail" => "API key invalid"]));
    }

    /** @throws ReflectionException */
    private function invokeParse(TavilySearchProvider $provider, Dictionary $body): WebSearchResult
    {
        $method = new ReflectionMethod(TavilySearchProvider::class, "parse");
        /** @var WebSearchResult $result */
        $result = $method->invoke($provider, $body);
        return $result;
    }
}
