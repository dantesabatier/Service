<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\URL;
use Sabatier\Service\RateLimitHeaderTransformer;
use Sabatier\Service\RateLimitInfo;
use Sabatier\Service\Response;
use Sabatier\Service\ResponseTransformerContext;

final class RateLimitHeaderTransformerTest extends TestCase
{
    private function response(): Response
    {
        return new Response(new URL("http://localhost/"));
    }

    private function transform(Response $response, ?RateLimitInfo $info): Response
    {
        return new RateLimitHeaderTransformer($response, new ResponseTransformerContext(rateLimitInfo: $info))->response;
    }

    // --- Sin info ---

    #[Test]
    public function noHeadersWhenNoRateLimitInfo(): void
    {
        $result = $this->transform($this->response(), null);
        $this->assertNull($result->allHeaderFields["X-RateLimit-Limit"]);
        $this->assertNull($result->allHeaderFields["X-RateLimit-Remaining"]);
        $this->assertNull($result->allHeaderFields["X-RateLimit-Reset"]);
    }

    // --- Headers emitidos ---

    #[Test]
    public function allThreeHeadersEmittedWhenInfoPresent(): void
    {
        $info = new RateLimitInfo(limit: 100, remaining: 42, reset: 1700000000);
        $result = $this->transform($this->response(), $info);
        $this->assertSame("100", $result->allHeaderFields["X-RateLimit-Limit"]);
        $this->assertSame("42", $result->allHeaderFields["X-RateLimit-Remaining"]);
        $this->assertSame("1700000000", $result->allHeaderFields["X-RateLimit-Reset"]);
    }

    #[Test]
    public function limitReflectsPolicy(): void
    {
        $result = $this->transform($this->response(), new RateLimitInfo(limit: 1000, remaining: 999, reset: 0));
        $this->assertSame("1000", $result->allHeaderFields["X-RateLimit-Limit"]);
    }

    #[Test]
    public function remainingReflectsCurrentWindow(): void
    {
        $result = $this->transform($this->response(), new RateLimitInfo(limit: 100, remaining: 0, reset: 0));
        $this->assertSame("0", $result->allHeaderFields["X-RateLimit-Remaining"]);
    }

    #[Test]
    public function resetIsUnixTimestamp(): void
    {
        $reset = time() + 60;
        $result = $this->transform($this->response(), new RateLimitInfo(limit: 100, remaining: 50, reset: $reset));
        $this->assertSame((string)$reset, $result->allHeaderFields["X-RateLimit-Reset"]);
    }
}
