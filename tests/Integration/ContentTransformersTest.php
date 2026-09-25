<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\URL;
use Sabatier\Service\HTMLTransformer;
use Sabatier\Service\NoCacheHeaderTransformer;
use Sabatier\Service\Response;
use Sabatier\Service\ResponseTransformerContext;

final class ContentTransformersTest extends TestCase
{
    private function response(): Response
    {
        return new Response(new URL("http://localhost/"));
    }

    // --- NoCacheHeaderTransformer ---

    #[Test]
    public function noCacheTransformerSetsCacheControlNoStore(): void
    {
        $result = new NoCacheHeaderTransformer($this->response(), new ResponseTransformerContext())->response;
        $this->assertSame("no-store, no-cache, must-revalidate, max-age=0", $result->allHeaderFields["Cache-Control"]);
    }

    #[Test]
    public function noCacheTransformerSetsPragma(): void
    {
        $result = new NoCacheHeaderTransformer($this->response(), new ResponseTransformerContext())->response;
        $this->assertSame("no-cache", $result->allHeaderFields["Pragma"]);
    }

    #[Test]
    public function noCacheTransformerSetsExpires(): void
    {
        $result = new NoCacheHeaderTransformer($this->response(), new ResponseTransformerContext())->response;
        $this->assertSame("0", $result->allHeaderFields["Expires"]);
    }

    #[Test]
    public function noCacheTransformerOverwritesExistingCacheControl(): void
    {
        $response = $this->response();
        $response->allHeaderFields["Cache-Control"] = "public, max-age=3600";
        $result = new NoCacheHeaderTransformer($response, new ResponseTransformerContext())->response;
        $this->assertSame("no-store, no-cache, must-revalidate, max-age=0", $result->allHeaderFields["Cache-Control"]);
    }

    // --- HTMLTransformer ---

    #[Test]
    public function htmlTransformerSetsContentType(): void
    {
        $result = new HTMLTransformer($this->response(), new ResponseTransformerContext())->response;
        $this->assertSame("text/html; charset=utf-8", $result->allHeaderFields["Content-Type"]);
    }

    #[Test]
    public function htmlTransformerOverwritesExistingContentType(): void
    {
        $response = $this->response();
        $response->allHeaderFields["Content-Type"] = "text/plain";
        $result = new HTMLTransformer($response, new ResponseTransformerContext())->response;
        $this->assertSame("text/html; charset=utf-8", $result->allHeaderFields["Content-Type"]);
    }

    #[Test]
    public function htmlTransformerDoesNotModifyBody(): void
    {
        $response = $this->response();
        $response->body = "<h1>Hello</h1>";
        $result = new HTMLTransformer($response, new ResponseTransformerContext())->response;
        $this->assertSame("<h1>Hello</h1>", $result->body);
    }
}
