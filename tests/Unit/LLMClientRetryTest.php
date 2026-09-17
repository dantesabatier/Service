<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\URL;
use Sabatier\Service\LLM\StandardLLMClient;

/**
 * Covers how the transport classifies a provider response and paces its retries.
 *
 * `send()` itself is not exercised here: it drives a real `URLSession`, and the suite has no seam for faking one. What it decides with — which statuses are worth another attempt, and how long to wait before it — is pure and is tested directly.
 */
final class LLMClientRetryTest extends TestCase
{
    #[Test]
    public function rateLimitingAndServerFailuresAreWorthRetrying(): void
    {
        $this->assertTrue($this->isRetryable(HTTPStatusCode::tooManyRequests));
        $this->assertTrue($this->isRetryable(HTTPStatusCode::internalServerError));
        $this->assertTrue($this->isRetryable(HTTPStatusCode::badGateway));
        $this->assertTrue($this->isRetryable(HTTPStatusCode::serviceUnavailable));
        $this->assertTrue($this->isRetryable(HTTPStatusCode::gatewayTimeout));
        $this->assertTrue($this->isRetryable(HTTPStatusCode::requestTimeout));
    }

    #[Test]
    public function faultsTheCallerCausedAreNotRetried(): void
    {
        $this->assertFalse($this->isRetryable(HTTPStatusCode::unauthorized));
        $this->assertFalse($this->isRetryable(HTTPStatusCode::forbidden));
        $this->assertFalse($this->isRetryable(HTTPStatusCode::badRequest));
        $this->assertFalse($this->isRetryable(HTTPStatusCode::notFound));
        $this->assertFalse($this->isRetryable(HTTPStatusCode::ok));
    }

    #[Test]
    public function delayDoublesWithEveryAttempt(): void
    {
        $client = new StandardLLMClient();

        $this->assertSame(1.0, $this->retryDelay($client, 0, null));
        $this->assertSame(2.0, $this->retryDelay($client, 1, null));
        $this->assertSame(4.0, $this->retryDelay($client, 2, null));
    }

    #[Test]
    public function delayNeverExceedsItsCeiling(): void
    {
        $client = new StandardLLMClient();

        $this->assertSame(30.0, $this->retryDelay($client, 20, null));
    }

    #[Test]
    public function providersRetryAfterWinsOverTheComputedDelay(): void
    {
        $client = new StandardLLMClient();

        $this->assertSame(7.0, $this->retryDelay($client, 0, $this->responseRetryingAfter("7")));
    }

    #[Test]
    public function anOversizedRetryAfterIsStillCapped(): void
    {
        $client = new StandardLLMClient();

        $this->assertSame(30.0, $this->retryDelay($client, 0, $this->responseRetryingAfter("3600")));
    }

    #[Test]
    public function aDateFormattedRetryAfterFallsBackToTheComputedDelay(): void
    {
        $client = new StandardLLMClient();

        $this->assertSame(1.0, $this->retryDelay($client, 0, $this->responseRetryingAfter("Wed, 21 Oct 2015 07:28:00 GMT")));
    }

    #[Test]
    public function aFailureWithoutAStatusIsReportedAsNoResponseAtAll(): void
    {
        $this->assertSame("The LLM provider returned no response", $this->failureReason(null, null));
    }

    #[Test]
    public function aFailingStatusIsNamedInTheFailureReason(): void
    {
        $this->assertSame("The LLM provider returned HTTP 503", $this->failureReason(HTTPStatusCode::serviceUnavailable, null));
    }

    #[Test]
    public function theProvidersOwnBodyIsAppendedToTheFailureReason(): void
    {
        $this->assertSame("The LLM provider returned HTTP 400: {\"error\":\"unknown model\"}", $this->failureReason(HTTPStatusCode::badRequest, "{\"error\":\"unknown model\"}"));
    }

    #[Test]
    public function aBlankBodyIsLeftOutOfTheFailureReason(): void
    {
        $this->assertSame("The LLM provider returned HTTP 500", $this->failureReason(HTTPStatusCode::internalServerError, "   \n  "));
    }

    #[Test]
    public function aBodylessFailureWithoutAStatusStillReadsCleanly(): void
    {
        $this->assertSame("The LLM provider returned no response: timed out", $this->failureReason(null, "timed out"));
    }

    private function failureReason(?int $statusCode, ?string $data): string
    {
        $client = new StandardLLMClient();
        /** @var string */
        return new ReflectionMethod($client, "failureReason")->invoke($client, $statusCode, $data);
    }

    private function isRetryable(int $statusCode): bool
    {
        $client = new StandardLLMClient();
        /** @var bool */
        return new ReflectionMethod($client, "isRetryable")->invoke($client, $statusCode);
    }

    private function retryDelay(StandardLLMClient $client, int $attempt, ?HTTPURLResponse $response): float
    {
        /** @var float */
        return new ReflectionMethod($client, "retryDelay")->invoke($client, $attempt, $response);
    }

    private function responseRetryingAfter(string $value): HTTPURLResponse
    {
        return new HTTPURLResponse(new URL("https://example.test/v1/chat/completions"), HTTPStatusCode::tooManyRequests, null, new Dictionary(["Retry-After" => $value]));
    }
}
