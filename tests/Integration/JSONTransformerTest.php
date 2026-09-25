<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use JsonException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;
use Sabatier\Service\JSONTransformer;
use Sabatier\Service\Response;
use Sabatier\Service\ResponseTransformerContext;

final class JSONTransformerTest extends TestCase
{
    private function response(mixed $body = null): Response
    {
        $response = new Response(new URL("http://localhost/"));
        $response->body = $body;
        return $response;
    }

    /** @throws JsonException */
    private function transform(Response $response): Response
    {
        return new JSONTransformer($response, new ResponseTransformerContext())->response;
    }

    /** @throws JsonException */
    #[Test]
    public function setsContentTypeToApplicationJson(): void
    {
        $result = $this->transform($this->response([]));
        $this->assertSame("application/json", $result->allHeaderFields["Content-Type"]);
    }

    /** @throws JsonException */
    #[Test]
    public function encodesArrayBody(): void
    {
        $result = $this->transform($this->response(["key" => "value"]));
        $this->assertSame("{\"key\":\"value\"}", $result->body);
    }

    /** @throws JsonException */
    #[Test]
    public function encodesNullBody(): void
    {
        /** @noinspection PhpRedundantOptionalArgumentInspection */
        $result = $this->transform($this->response(null));
        $this->assertSame("null", $result->body);
    }

    /** @throws JsonException */
    #[Test]
    public function encodesStringBody(): void
    {
        $result = $this->transform($this->response("hello"));
        $this->assertSame("\"hello\"", $result->body);
    }

    /** @throws JsonException */
    #[Test]
    public function encodesIntBody(): void
    {
        $result = $this->transform($this->response(42));
        $this->assertSame("42", $result->body);
    }

    /** @throws JsonException */
    #[Test]
    public function preservesZeroFraction(): void
    {
        $result = $this->transform($this->response(1.0));
        $this->assertSame("1.0", $result->body);
    }

    /** @throws JsonException */
    #[Test]
    public function encodesDictionary(): void
    {
        $dict = new Dictionary(["status" => "ok", "count" => 3]);
        $result = $this->transform($this->response($dict));
        $decoded = json_decode($result->body, true);
        $this->assertSame("ok", $decoded["status"]);
        $this->assertSame(3, $decoded["count"]);
    }

    /** @throws JsonException */
    #[Test]
    public function encodesNestedStructure(): void
    {
        $result = $this->transform($this->response(["items" => [1, 2, 3], "total" => 3]));
        $decoded = json_decode($result->body, true);
        $this->assertSame([1, 2, 3], $decoded["items"]);
        $this->assertSame(3, $decoded["total"]);
    }

    /** @throws JsonException */
    #[Test]
    public function throwsOnUnencodableValue(): void
    {
        $this->expectException(JsonException::class);
        $response = $this->response(fopen("php://memory", "r"));
        $this->transform($response);
    }
}
