<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Set;
use Sabatier\Service\Authentication;
use Sabatier\Service\AuthenticationContext;
use Sabatier\Service\AuthenticationScheme;
use Sabatier\Service\AuthenticationService;
use Sabatier\Service\Authorizable;
use Sabatier\Service\AuthorizationHeader;
use Sabatier\Service\DigestAuthentication;

final class DigestAuthenticationTest extends TestCase
{
    /** @return iterable<string, array{AuthenticationScheme, bool}> */
    public static function schemeProvider(): iterable
    {
        yield "digest" => [AuthenticationScheme::digest, true];
        yield "basic" => [AuthenticationScheme::basic, false];
        yield "bearer" => [AuthenticationScheme::bearer, false];
        yield "negotiate" => [AuthenticationScheme::negotiate, false];
        yield "aws" => [AuthenticationScheme::aws, false];
    }

    #[Test]
    #[DataProvider("schemeProvider")]
    public function supportsOnlyTheDigestScheme(AuthenticationScheme $scheme, bool $expected): void
    {
        $this->assertSame($expected, DigestAuthentication::isSupported($scheme));
    }

    /** @throws ReflectionException */
    #[Test]
    public function reportsItsOwnScheme(): void
    {
        $this->assertSame(AuthenticationScheme::digest, $this->authentication("")->scheme);
    }

    /** @throws ReflectionException */
    #[Test]
    public function parsesEveryRecognisedDigestParameter(): void
    {
        $parameters = $this->authentication("username=\"ada\", uri=\"/orders\", nonce=\"abc\", nc=00000001, cnonce=\"xyz\", qop=auth, algorithm=SHA-256, response=\"deadbeef\", opaque=\"op\"")->parameters;
        $this->assertSame("ada", $parameters["username"]);
        $this->assertSame("/orders", $parameters["uri"]);
        $this->assertSame("abc", $parameters["nonce"]);
        $this->assertSame("00000001", $parameters["nc"]);
        $this->assertSame("xyz", $parameters["cnonce"]);
        $this->assertSame("auth", $parameters["qop"]);
        $this->assertSame("SHA-256", $parameters["algorithm"]);
        $this->assertSame("deadbeef", $parameters["response"]);
        $this->assertSame("op", $parameters["opaque"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function ignoresParametersItDoesNotRecognise(): void
    {
        $this->assertNull($this->authentication("username=\"ada\", stale=TRUE")->parameters["stale"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function parsesUnquotedParameters(): void
    {
        $this->assertSame("ada", $this->authentication("username=ada")->parameters["username"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theParsedParametersAreMemoized(): void
    {
        $authentication = $this->authentication("username=\"ada\"");
        $this->assertSame($authentication->parameters, $authentication->parameters);
    }

    /** @throws ReflectionException */
    #[Test]
    public function derivesTheCredentialFromTheUsernameParameter(): void
    {
        $this->assertSame("ada", $this->authentication("username=\"ada\"")->credential?->user);
    }

    /** @throws ReflectionException */
    #[Test]
    public function thereIsNoCredentialWithoutAUsername(): void
    {
        $this->assertNull($this->authentication("nonce=\"abc\"")->credential);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theCredentialIsResolvedOnlyOnce(): void
    {
        $authentication = $this->authentication("username=\"ada\"");
        $this->assertSame($authentication->credential, $authentication->credential);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aCredentiallessHeaderIsNotValid(): void
    {
        $this->assertFalse($this->authentication("nonce=\"abc\"")->isValid);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aUserWithoutAPasswordIsNotValid(): void
    {
        $this->assertFalse($this->authenticationForUser("username=\"ada\", uri=\"/orders\", nonce=\"abc\", nc=1, cnonce=\"xyz\", qop=auth, response=\"x\"", null)->isValid);
    }

    /** @return iterable<string, array{string}> */
    public static function missingParameterProvider(): iterable
    {
        yield "no uri" => ["nonce=\"abc\", nc=1, cnonce=\"xyz\", qop=auth"];
        yield "no nonce" => ["uri=\"/orders\", nc=1, cnonce=\"xyz\", qop=auth"];
        yield "no nc" => ["uri=\"/orders\", nonce=\"abc\", cnonce=\"xyz\", qop=auth"];
        yield "no cnonce" => ["uri=\"/orders\", nonce=\"abc\", nc=1, qop=auth"];
        yield "no qop" => ["uri=\"/orders\", nonce=\"abc\", nc=1, cnonce=\"xyz\""];
    }

    /** @throws ReflectionException */
    #[Test]
    #[DataProvider("missingParameterProvider")]
    public function aChallengeMissingAnyRequiredParameterIsNotValid(string $header): void
    {
        $this->assertFalse($this->authenticationForUser("username=\"ada\", $header, response=\"x\"", "secret")->isValid);
    }

    /** @return iterable<string, array{string, string}> */
    public static function algorithmProvider(): iterable
    {
        yield "SHA-256 hashes with sha256" => ["SHA-256", "sha256"];
        yield "SHA-512-256 hashes with sha512" => ["SHA-512-256", "sha512"];
        yield "MD5 falls through to md5" => ["MD5", "md5"];
        yield "an unknown algorithm falls through to md5" => ["SHA-1", "md5"];
    }

    /** @throws ReflectionException */
    #[Test]
    #[DataProvider("algorithmProvider")]
    public function acceptsTheResponseComputedWithTheNamedAlgorithm(string $algorithm, string $hash): void
    {
        $header = $this->challenge($this->expectedResponse($hash), $algorithm);
        $this->assertTrue($this->authenticationForUser($header, "secret")->isValid);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anAbsentAlgorithmFallsThroughToMD5(): void
    {
        $this->assertTrue($this->authenticationForUser($this->challenge($this->expectedResponse("md5")), "secret")->isValid);
    }

    /** @throws ReflectionException */
    #[Test]
    public function rejectsAResponseComputedWithADifferentAlgorithm(): void
    {
        $this->assertFalse($this->authenticationForUser($this->challenge($this->expectedResponse("md5"), "SHA-256"), "secret")->isValid);
    }

    /** @throws ReflectionException */
    #[Test]
    public function rejectsAMismatchedResponse(): void
    {
        $this->assertFalse($this->authenticationForUser($this->challenge("not the right digest"), "secret")->isValid);
    }

    /** @throws ReflectionException */
    #[Test]
    public function rejectsAResponseComputedFromADifferentPassword(): void
    {
        $this->assertFalse($this->authenticationForUser($this->challenge($this->expectedResponse("md5")), "a different secret")->isValid);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theVerdictIsMemoized(): void
    {
        $authentication = $this->authenticationForUser($this->challenge($this->expectedResponse("md5")), "secret");
        $this->assertTrue($authentication->isValid);
        $this->assertTrue($authentication->isValid);
    }

    private function challenge(string $response, ?string $algorithm = null): string
    {
        $header = "username=\"ada\", uri=\"/orders\", nonce=\"abc\", nc=00000001, cnonce=\"xyz\", qop=auth";
        if ($algorithm) {
            $header .= ", algorithm=$algorithm";
        }
        return "$header, response=\"$response\"";
    }

    private function expectedResponse(string $hash, string $password = "secret"): string
    {
        $HA2 = hash($hash, HTTPRequestMethod::get . ":/orders");
        return hash($hash, "$password:abc:00000001:xyz:auth:$HA2");
    }

    /** @throws ReflectionException */
    private function authentication(string $headerValue): DigestAuthentication
    {
        $context = new AuthenticationContext(
            new AuthorizationHeader("Digest $headerValue"),
            HTTPRequestMethod::get,
            new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor(),
            null,
            new ReflectionClass(AuthenticationService::class)->newInstanceWithoutConstructor()
        );
        return new DigestAuthentication($context, new Dictionary());
    }

    /** @throws ReflectionException */
    private function authenticationForUser(string $headerValue, ?string $password): DigestAuthentication
    {
        $authentication = $this->authentication($headerValue);
        new ReflectionProperty(Authentication::class, "isAuthenticatedUserResolved")->setValue($authentication, true);
        new ReflectionProperty(Authentication::class, "authenticatedUser")->setRawValue($authentication, $this->user($password));
        return $authentication;
    }

    private function user(?string $password): Authorizable
    {
        return new class ($password) implements Authorizable {
            public function __construct(private readonly ?string $secret)
            {
            }

            public string $username { get => "ada"; }
            public ?string $password { get => $this->secret; }
            public bool $isEnabled { get => true; }
            public int $refreshTokenVersion { get => 1; set {} }
            public Set $roles { get => new Set(); }

            #[Override]
            public function isEqual(mixed $other): bool
            {
                return $this === $other;
            }

            #[Override]
            public static function defaultRepresentation(): Dictionary
            {
                return new Dictionary();
            }
        };
    }
}
