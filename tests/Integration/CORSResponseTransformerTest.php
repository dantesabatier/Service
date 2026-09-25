<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Sabatier\Service\CORSPolicy;
use Sabatier\Service\CORSResponseTransformer;
use Sabatier\Service\Request;
use Sabatier\Service\Response;
use Sabatier\Service\ResponseTransformerContext;

final class CORSResponseTransformerTest extends TestCase
{
    private array $originalServer;

    #[Override]
    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REQUEST_URI"] = "/";
        $_SERVER["REQUEST_METHOD"] = "GET";
        unset($_SERVER["HTTPS"]);
    }

    #[Override]
    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    private function request(string $origin = "", string $requestedHeaders = ""): Request
    {
        if ($origin !== "") {
            $_SERVER["HTTP_ORIGIN"] = $origin;
        } else {
            unset($_SERVER["HTTP_ORIGIN"]);
        }
        if ($requestedHeaders !== "") {
            $_SERVER["HTTP_ACCESS_CONTROL_REQUEST_HEADERS"] = $requestedHeaders;
        } else {
            unset($_SERVER["HTTP_ACCESS_CONTROL_REQUEST_HEADERS"]);
        }
        return new Request();
    }

    private function response(): Response
    {
        return new Response(new URL("http://localhost/"));
    }

    private function transform(Response $response, CORSPolicy $policy, Request $request): Response
    {
        return new CORSResponseTransformer($response, new ResponseTransformerContext(request: $request, corsPolicy: $policy))->response;
    }

    // --- Sin política / sin Origin ---

    #[Test]
    public function noCORSHeadersWhenNoPolicyConfigured(): void
    {
        $response = $this->transform($this->response(), new CORSPolicy(), $this->request("https://example.com"));
        $this->assertNull($response->allHeaderFields["Access-Control-Allow-Origin"]);
    }

    #[Test]
    public function noCORSHeadersWhenRequestHasNoOrigin(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["*"]));
        /** @noinspection PhpRedundantOptionalArgumentInspection */
        $response = $this->transform($this->response(), $policy, $this->request(""));
        $this->assertNull($response->allHeaderFields["Access-Control-Allow-Origin"]);
    }

    #[Test]
    public function noCORSHeadersWhenOriginIsNotAllowed(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["https://allowed.com"]));
        $response = $this->transform($this->response(), $policy, $this->request("https://attacker.com"));
        $this->assertNull($response->allHeaderFields["Access-Control-Allow-Origin"]);
    }

    // --- Wildcard ---

    #[Test]
    public function wildcardPolicyEmitsStarOriginWithoutVary(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["*"]));
        $response = $this->transform($this->response(), $policy, $this->request("https://example.com"));
        $headers = $response->allHeaderFields;
        $this->assertSame("*", $headers["Access-Control-Allow-Origin"]);
        $this->assertNull($headers["Vary"]);
    }

    #[Test]
    public function wildcardPolicyWithCredentialsReflectsOriginAndAddsVary(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["*"]), allowCredentials: true);
        $response = $this->transform($this->response(), $policy, $this->request("https://example.com"));
        $headers = $response->allHeaderFields;
        $this->assertSame("https://example.com", $headers["Access-Control-Allow-Origin"]);
        $this->assertSame("Origin", $headers["Vary"]);
        $this->assertSame("true", $headers["Access-Control-Allow-Credentials"]);
    }

    // --- Origen exacto ---

    #[Test]
    public function exactOriginPolicyReflectsOriginAndAddsVary(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["https://example.com"]));
        $response = $this->transform($this->response(), $policy, $this->request("https://example.com"));
        $headers = $response->allHeaderFields;
        $this->assertSame("https://example.com", $headers["Access-Control-Allow-Origin"]);
        $this->assertSame("Origin", $headers["Vary"]);
    }

    // --- Credenciales ---

    #[Test]
    public function credentialsHeaderEmittedWhenEnabled(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["https://example.com"]), allowCredentials: true);
        $response = $this->transform($this->response(), $policy, $this->request("https://example.com"));
        $this->assertSame("true", $response->allHeaderFields["Access-Control-Allow-Credentials"]);
    }

    #[Test]
    public function credentialsHeaderAbsentWhenDisabled(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["https://example.com"]));
        $response = $this->transform($this->response(), $policy, $this->request("https://example.com"));
        $this->assertNull($response->allHeaderFields["Access-Control-Allow-Credentials"]);
    }

    // --- Métodos ---

    #[Test]
    public function allowedMethodsHeaderEmitted(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["*"]), allowedMethods: new Set(["get", "post"]));
        $response = $this->transform($this->response(), $policy, $this->request("https://example.com"));
        $methods = $response->allHeaderFields["Access-Control-Allow-Methods"];
        $this->assertStringContainsString("GET", $methods);
        $this->assertStringContainsString("POST", $methods);
    }

    #[Test]
    public function allowedMethodsHeaderAbsentWhenNoneConfigured(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["*"]));
        $response = $this->transform($this->response(), $policy, $this->request("https://example.com"));
        $this->assertNull($response->allHeaderFields["Access-Control-Allow-Methods"]);
    }

    // --- Headers ---

    #[Test]
    public function allowedHeadersReflectIntersectionWithRequested(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["*"]), allowedHeaders: new Set(["Authorization", "Content-Type"]));
        $response = $this->transform($this->response(), $policy, $this->request("https://example.com", "authorization, x-custom"));
        $allowedHeaders = $response->allHeaderFields["Access-Control-Allow-Headers"];
        $this->assertStringContainsString("authorization", $allowedHeaders);
        $this->assertStringNotContainsString("x-custom", $allowedHeaders);
    }

    #[Test]
    public function allowedHeadersAbsentWhenNoIntersection(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["*"]), allowedHeaders: new Set(["Authorization"]));
        $response = $this->transform($this->response(), $policy, $this->request("https://example.com", "x-custom"));
        $this->assertNull($response->allHeaderFields["Access-Control-Allow-Headers"]);
    }

    #[Test]
    public function allowedHeadersAbsentWhenNoRequestedHeaders(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["*"]), allowedHeaders: new Set(["Authorization"]));
        $response = $this->transform($this->response(), $policy, $this->request("https://example.com"));
        $this->assertNull($response->allHeaderFields["Access-Control-Allow-Headers"]);
    }

    // --- Exposed headers ---

    #[Test]
    public function exposedHeadersEmittedWhenConfigured(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["*"]), exposedHeaders: new Set(["X-Request-Id"]));
        $response = $this->transform($this->response(), $policy, $this->request("https://example.com"));
        $this->assertSame("X-Request-Id", $response->allHeaderFields["Access-Control-Expose-Headers"]);
    }

    #[Test]
    public function exposedHeadersAbsentWhenNoneConfigured(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["*"]));
        $response = $this->transform($this->response(), $policy, $this->request("https://example.com"));
        $this->assertNull($response->allHeaderFields["Access-Control-Expose-Headers"]);
    }
}
