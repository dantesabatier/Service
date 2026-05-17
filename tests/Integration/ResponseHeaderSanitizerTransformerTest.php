<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\URL;
use Sabatier\Service\Response;
use Sabatier\Service\ResponseHeaderSanitizerTransformer;
use Sabatier\Service\ResponseTransformerContext;

final class ResponseHeaderSanitizerTransformerTest extends TestCase
{
    private function response(int $status): Response
    {
        $response = new Response(new URL('http://localhost/'), $status);
        $response->allHeaderFields['Content-Type'] = 'application/json';
        $response->allHeaderFields['Content-Length'] = '42';
        $response->allHeaderFields['Content-Disposition'] = 'attachment; filename="file.json"';
        $response->allHeaderFields['X-Custom'] = 'preserved';
        return $response;
    }

    private function transform(Response $response): Response
    {
        return (new ResponseHeaderSanitizerTransformer($response, new ResponseTransformerContext()))->response;
    }

    // --- Status codes que prohíben body ---

    #[Test]
    public function contentHeadersRemovedFor204(): void
    {
        $result = $this->transform($this->response(HTTPStatusCode::noContent));
        $this->assertNull($result->allHeaderFields['Content-Type']);
        $this->assertNull($result->allHeaderFields['Content-Length']);
        $this->assertNull($result->allHeaderFields['Content-Disposition']);
    }

    #[Test]
    public function contentHeadersRemovedFor304(): void
    {
        $result = $this->transform($this->response(HTTPStatusCode::notModified));
        $this->assertNull($result->allHeaderFields['Content-Type']);
        $this->assertNull($result->allHeaderFields['Content-Length']);
        $this->assertNull($result->allHeaderFields['Content-Disposition']);
    }

    #[Test]
    public function contentHeadersRemovedFor205(): void
    {
        $result = $this->transform($this->response(HTTPStatusCode::resetContent));
        $this->assertNull($result->allHeaderFields['Content-Type']);
        $this->assertNull($result->allHeaderFields['Content-Length']);
        $this->assertNull($result->allHeaderFields['Content-Disposition']);
    }

    // --- Status codes que permiten body ---

    #[Test]
    public function contentHeadersPreservedFor200(): void
    {
        $result = $this->transform($this->response(HTTPStatusCode::ok));
        $this->assertSame('application/json', $result->allHeaderFields['Content-Type']);
        $this->assertSame('42', $result->allHeaderFields['Content-Length']);
        $this->assertSame('attachment; filename="file.json"', $result->allHeaderFields['Content-Disposition']);
    }

    #[Test]
    public function contentHeadersPreservedFor201(): void
    {
        $result = $this->transform($this->response(HTTPStatusCode::created));
        $this->assertSame('application/json', $result->allHeaderFields['Content-Type']);
    }

    #[Test]
    public function contentHeadersPreservedFor404(): void
    {
        $result = $this->transform($this->response(HTTPStatusCode::notFound));
        $this->assertSame('application/json', $result->allHeaderFields['Content-Type']);
    }

    // --- Headers no relacionados siempre se preservan ---

    #[Test]
    public function nonContentHeadersPreservedFor204(): void
    {
        $result = $this->transform($this->response(HTTPStatusCode::noContent));
        $this->assertSame('preserved', $result->allHeaderFields['X-Custom']);
    }

    #[Test]
    public function nonContentHeadersPreservedFor304(): void
    {
        $result = $this->transform($this->response(HTTPStatusCode::notModified));
        $this->assertSame('preserved', $result->allHeaderFields['X-Custom']);
    }
}
