<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Exception;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\ConflictException;
use Sabatier\Service\ErrorResponder;
use Sabatier\Service\ForbiddenException;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\Response;
use Sabatier\Service\TooManyRequestsException;
use Sabatier\Service\UnauthorizedException;
use Throwable;

final class ErrorResponderTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server;

    /**
     * Resolving the responder's session creates the bundle's caches tree under the project, which is
     * not a build artifact the repository ignores, so the suite takes it away again once it is done.
     * @throws Exception
     */
    public static function tearDownAfterClass(): void
    {
        FileManager::default()->removeItem(FileManager::default()->url(SearchPathDirectory::cachesDirectory)->deletingLastPathComponent());
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REQUEST_URI"] = "/orders";
        $_SERVER["REQUEST_METHOD"] = "GET";
        unset($_SERVER["HTTP_AUTHORIZATION"]);
        $this->forgetMemoizedRequest();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $this->forgetMemoizedRequest();
        parent::tearDown();
    }

    /** @throws JsonException */
    #[Test]
    public function anUnrecognisedThrowableBecomesAnInternalServerError(): void
    {
        $response = $this->respondTo(new RuntimeException("the disk caught fire"));
        $this->assertSame(HTTPStatusCode::internalServerError, $response->statusCode);
        $this->assertSame("Internal server error", $this->error($response)["localizedDescription"]);
    }

    /** @throws JsonException */
    #[Test]
    public function anUnrecognisedThrowableDoesNotLeakItsMessageOutsideDevelopment(): void
    {
        $this->assertSame("", $this->error($this->respondTo(new RuntimeException("the disk caught fire")))["localizedFailureReason"]);
    }

    #[Test]
    public function anInvalidRequestExceptionCarriesItsOwnStatusCode(): void
    {
        $this->assertSame(HTTPStatusCode::notFound, $this->respondTo(new NotFoundException("no such order"))->statusCode);
        $this->assertSame(HTTPStatusCode::badRequest, $this->respondTo(new BadRequestException("malformed"))->statusCode);
        $this->assertSame(HTTPStatusCode::forbidden, $this->respondTo(new ForbiddenException("not yours"))->statusCode);
        $this->assertSame(HTTPStatusCode::conflict, $this->respondTo(new ConflictException("already there"))->statusCode);
    }

    /** @throws JsonException */
    #[Test]
    public function anInvalidRequestExceptionSurfacesItsFailureReason(): void
    {
        $error = $this->error($this->respondTo(new NotFoundException("no such order")));
        $this->assertSame("Not found", $error["localizedDescription"]);
        $this->assertSame("no such order", $error["localizedFailureReason"]);
    }

    /** @throws JsonException */
    #[Test]
    public function theBodyIsAlwaysKeyedUnderError(): void
    {
        $this->assertArrayHasKey("error", json_decode((string)$this->respondTo(new NotFoundException("gone"))->body, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function anUnauthorizedRequestIsChallengedWithBasicByDefault(): void
    {
        $response = $this->respondTo(new UnauthorizedException("sign in"));
        $this->assertSame(HTTPStatusCode::unauthorized, $response->statusCode);
        $this->assertSame("Basic realm=\"localhost\"", $response->allHeaderFields["WWW-Authenticate"]);
    }

    #[Test]
    public function aBearerCredentialIsChallengedWithBearerAndTheFailureReason(): void
    {
        $_SERVER["HTTP_AUTHORIZATION"] = "Bearer expired.token.here";
        $challenge = (string)$this->respondTo(new UnauthorizedException("the token has expired"))->allHeaderFields["WWW-Authenticate"];
        $this->assertStringStartsWith("Bearer realm=\"localhost\"", $challenge);
        $this->assertStringContainsString("error=\"Unauthorized\"", $challenge);
        $this->assertStringContainsString("error_description=\"the token has expired\"", $challenge);
    }

    #[Test]
    public function aDigestCredentialIsChallengedWithADigestNonceAndOpaqueRealm(): void
    {
        $_SERVER["HTTP_AUTHORIZATION"] = "Digest username=\"ada\"";
        $challenge = (string)$this->respondTo(new UnauthorizedException("stale"))->allHeaderFields["WWW-Authenticate"];
        $this->assertStringStartsWith("Digest realm=\"localhost\"", $challenge);
        $this->assertStringContainsString("uri=\"/orders\"", $challenge);
        $this->assertStringContainsString("algorithm=\"SHA-256\"", $challenge);
        $this->assertStringContainsString("qop=\"auth\"", $challenge);
        $this->assertStringContainsString("opaque=\"bG9jYWxob3N0\"", $challenge);
    }

    #[Test]
    public function aNegotiateCredentialIsChallengedWithItsOwnSchemeName(): void
    {
        $_SERVER["HTTP_AUTHORIZATION"] = "Negotiate abcdef";
        $this->assertSame("Negotiate realm=\"localhost\"", $this->respondTo(new UnauthorizedException("no"))->allHeaderFields["WWW-Authenticate"]);
    }

    #[Test]
    public function anUnrecognisedSchemeIsChallengedWithBasic(): void
    {
        $_SERVER["HTTP_AUTHORIZATION"] = "Hawk id=\"ada\"";
        $this->assertSame("Basic realm=\"localhost\"", $this->respondTo(new UnauthorizedException("no"))->allHeaderFields["WWW-Authenticate"]);
    }

    #[Test]
    public function aThrottledRequestCarriesItsRetryAfterDelay(): void
    {
        $response = $this->respondTo(new TooManyRequestsException(42));
        $this->assertSame(HTTPStatusCode::tooManyRequests, $response->statusCode);
        $this->assertSame("42", $response->allHeaderFields["Retry-After"]);
    }

    #[Test]
    public function onlyAnUnauthorizedResponseIsChallenged(): void
    {
        $this->assertNull($this->respondTo(new NotFoundException("gone"))->allHeaderFields["WWW-Authenticate"]);
    }

    #[Test]
    public function onlyAThrottledResponseCarriesRetryAfter(): void
    {
        $this->assertNull($this->respondTo(new NotFoundException("gone"))->allHeaderFields["Retry-After"]);
    }

    #[Test]
    public function theResponseIsServedAsJSON(): void
    {
        $this->assertStringContainsString("application/json", (string)$this->respondTo(new NotFoundException("gone"))->allHeaderFields["Content-Type"]);
    }

    private function respondTo(Throwable $throwable): Response
    {
        $responder = new ErrorResponder();
        new ReflectionProperty(ErrorResponder::class, "throwable")->setValue($responder, $throwable);
        return $responder->response;
    }

    /**
     * @return array{localizedDescription?: string, localizedFailureReason?: string}
     * @throws JsonException
     */
    private function error(Response $response): array
    {
        /** @var array{error: array{localizedDescription?: string, localizedFailureReason?: string}} $body */
        $body = json_decode((string)$response->body, true, 512, JSON_THROW_ON_ERROR);
        return $body["error"];
    }

    /**
     * Responder memoizes its Request in a static shared by every responder, so a request built for
     * one test would otherwise be reused by all the others.
     */
    private function forgetMemoizedRequest(): void
    {
        ObjectClass::$staticAssociatedValues = [];
    }
}
