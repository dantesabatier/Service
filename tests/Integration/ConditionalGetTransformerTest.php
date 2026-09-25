<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\URL;
use Sabatier\Service\ConditionalGetTransformer;
use Sabatier\Service\HTTPCachePolicy;
use Sabatier\Service\Request;
use Sabatier\Service\Response;
use Sabatier\Service\ResponseTransformerContext;

final class ConditionalGetTransformerTest extends TestCase
{
    private array $originalServer;

    #[Override]
    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REQUEST_URI"] = "/";
        $_SERVER["REQUEST_METHOD"] = "GET";
        unset($_SERVER["HTTPS"], $_SERVER["HTTP_IF_NONE_MATCH"]);
    }

    #[Override]
    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    private function request(string $ifNoneMatch = ""): Request
    {
        if ($ifNoneMatch !== "") {
            $_SERVER["HTTP_IF_NONE_MATCH"] = $ifNoneMatch;
        } else {
            unset($_SERVER["HTTP_IF_NONE_MATCH"]);
        }
        return new Request();
    }

    private function response(string $body = "hello", int $status = HTTPStatusCode::ok, string $cacheControl = "public, max-age=3600"): Response
    {
        $response = new Response(new URL("http://localhost/"), $status);
        $response->body = $body;
        if ($cacheControl !== "") {
            $response->allHeaderFields["Cache-Control"] = $cacheControl;
        }
        return $response;
    }

    private function transform(Response $response, Request $request, ?HTTPCachePolicy $policy = new HTTPCachePolicy()): Response
    {
        return new ConditionalGetTransformer($response, new ResponseTransformerContext(request: $request, cachePolicy: $policy))->response;
    }

    private function etag(string $body): string
    {
        return "\"" . md5($body) . "\"";
    }

    // --- Condiciones que omiten ETag ---

    #[Test]
    public function noETagWhenNoCacheControlHeader(): void
    {
        $response = new Response(new URL("http://localhost/"));
        $response->body = "hello";
        $result = $this->transform($response, $this->request());
        $this->assertNull($result->allHeaderFields["ETag"]);
    }

    #[Test]
    public function noETagWhenCacheControlIsNoStore(): void
    {
        $result = $this->transform($this->response(cacheControl: "no-store"), $this->request());
        $this->assertNull($result->allHeaderFields["ETag"]);
    }

    #[Test]
    public function noETagWhenETagDisabledInPolicy(): void
    {
        $result = $this->transform($this->response(), $this->request(), new HTTPCachePolicy(etagEnabled: false));
        $this->assertNull($result->allHeaderFields["ETag"]);
    }

    #[Test]
    public function noETagWhenResponseStatusIsNotSuccess(): void
    {
        $result = $this->transform($this->response(status: HTTPStatusCode::notFound), $this->request());
        $this->assertNull($result->allHeaderFields["ETag"]);
    }

    #[Test]
    public function noETagWhenNoRequestInContext(): void
    {
        $response = $this->response();
        $result = new ConditionalGetTransformer($response, new ResponseTransformerContext(cachePolicy: new HTTPCachePolicy()))->response;
        $this->assertNull($result->allHeaderFields["ETag"]);
    }

    #[Test]
    public function noETagForNonGetRequest(): void
    {
        $_SERVER["REQUEST_METHOD"] = "POST";
        $result = $this->transform($this->response(), new Request());
        $_SERVER["REQUEST_METHOD"] = "GET";
        $this->assertNull($result->allHeaderFields["ETag"]);
    }

    // --- ETag generado ---

    #[Test]
    public function eTagAddedForCacheableGetResponse(): void
    {
        $body = "hello world";
        $result = $this->transform($this->response($body), $this->request());
        $this->assertSame($this->etag($body), $result->allHeaderFields["ETag"]);
    }

    #[Test]
    public function eTagIsDeterministic(): void
    {
        $body = "same body";
        $first = $this->transform($this->response($body), $this->request());
        $second = $this->transform($this->response($body), $this->request());
        $this->assertSame($first->allHeaderFields["ETag"], $second->allHeaderFields["ETag"]);
    }

    #[Test]
    public function differentBodiesProduceDifferentETags(): void
    {
        $a = $this->transform($this->response("body-a"), $this->request());
        $b = $this->transform($this->response("body-b"), $this->request());
        $this->assertNotSame($a->allHeaderFields["ETag"], $b->allHeaderFields["ETag"]);
    }

    #[Test]
    public function statusIs201ETagStillGenerated(): void
    {
        $result = $this->transform($this->response(status: HTTPStatusCode::created), $this->request());
        $this->assertNotNull($result->allHeaderFields["ETag"]);
    }

    // --- 304 Not Modified ---

    #[Test]
    public function returns304WhenIfNoneMatchMatchesETag(): void
    {
        $body = "cached body";
        $etag = $this->etag($body);
        $result = $this->transform($this->response($body), $this->request($etag));
        $this->assertSame(HTTPStatusCode::notModified, $result->statusCode);
    }

    #[Test]
    public function returns304ForWildcardIfNoneMatch(): void
    {
        $result = $this->transform($this->response("any body"), $this->request("*"));
        $this->assertSame(HTTPStatusCode::notModified, $result->statusCode);
    }

    #[Test]
    public function returns304WhenETagIsInCommaSeparatedList(): void
    {
        $body = "body";
        $etag = $this->etag($body);
        $result = $this->transform($this->response($body), $this->request("\"stale-tag\", " . $etag . ", \"other\""));
        $this->assertSame(HTTPStatusCode::notModified, $result->statusCode);
    }

    #[Test]
    public function returns200WhenIfNoneMatchDoesNotMatch(): void
    {
        $result = $this->transform($this->response("body"), $this->request("\"different-etag\""));
        $this->assertSame(HTTPStatusCode::ok, $result->statusCode);
    }

    // --- Headers en la respuesta 304 ---

    #[Test]
    public function notModifiedResponsePreservesETagHeader(): void
    {
        $body = "body";
        $etag = $this->etag($body);
        $result = $this->transform($this->response($body), $this->request($etag));
        $this->assertSame($etag, $result->allHeaderFields["ETag"]);
    }

    #[Test]
    public function notModifiedResponsePreservesCacheControl(): void
    {
        $body = "body";
        $etag = $this->etag($body);
        $result = $this->transform($this->response($body, cacheControl: "public, max-age=600"), $this->request($etag));
        $this->assertSame("public, max-age=600", $result->allHeaderFields["Cache-Control"]);
    }

    #[Test]
    public function notModifiedResponsePropagatesVaryHeader(): void
    {
        $body = "body";
        $response = $this->response($body);
        $response->allHeaderFields["Vary"] = "Accept-Encoding";
        $etag = $this->etag($body);
        $result = $this->transform($response, $this->request($etag));
        $this->assertSame("Accept-Encoding", $result->allHeaderFields["Vary"]);
    }

    #[Test]
    public function notModifiedResponseHasNoBody(): void
    {
        $body = "body";
        $etag = $this->etag($body);
        $result = $this->transform($this->response($body), $this->request($etag));
        $this->assertNull($result->body);
    }
}
