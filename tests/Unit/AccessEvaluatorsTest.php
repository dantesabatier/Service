<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\Set;
use Sabatier\Service\AccessEvaluationContext;
use Sabatier\Service\Authentication;
use Sabatier\Service\AuthenticationEvaluator;
use Sabatier\Service\AuthenticationScheme;
use Sabatier\Service\Authorizable;
use Sabatier\Service\BearerAuthentication;
use Sabatier\Service\JSONWebToken;
use Sabatier\Service\JSONWebTokenAccessTimeEvaluator;
use Sabatier\Service\JSONWebTokenEnabledEvaluator;
use Sabatier\Service\JSONWebTokenHeader;
use Sabatier\Service\JSONWebTokenPayload;
use Sabatier\Service\JSONWebTokenRefreshTimeEvaluator;
use Sabatier\Service\JSONWebTokenScopeEvaluator;
use Sabatier\Service\JSONWebTokenVersionEvaluator;

final class AccessEvaluatorsTest extends TestCase
{
    private function contextWithAuth(Authentication $authentication): AccessEvaluationContext
    {
        $r = new ReflectionClass(AccessEvaluationContext::class);
        $context = $r->newInstanceWithoutConstructor();
        $r->getProperty('authentication')->setValue($context, $authentication);
        return $context;
    }

    private function makeAuth(bool $isValid, ?ArrayClass $technicalScopes = null): Authentication
    {
        $auth = new class($isValid) extends Authentication {
            public function __construct(private readonly bool $_valid) {}
            public AuthenticationScheme $scheme { get => AuthenticationScheme::bearer; }
            public ?URLCredential $credential { get => null; }
            public bool $isValid { get => $this->_valid; }
            #[Override]
            public static function isSupported(AuthenticationScheme $scheme): bool { return false; }
        };
        if ($technicalScopes !== null) {
            (new ReflectionClass(Authentication::class))
                ->getProperty('technicalScopes')
                ->setValue($auth, $technicalScopes);
        }
        return $auth;
    }

    // --- AuthenticationEvaluator ---

    #[Test]
    public function authenticationEvaluatorAllowsValidAuth(): void
    {
        $this->assertTrue(
            (new AuthenticationEvaluator())->evaluate($this->contextWithAuth($this->makeAuth(true)))
        );
    }

    #[Test]
    public function authenticationEvaluatorDeniesInvalidAuth(): void
    {
        $this->assertFalse(
            (new AuthenticationEvaluator())->evaluate($this->contextWithAuth($this->makeAuth(false)))
        );
    }

    // --- JSONWebTokenScopeEvaluator ---

    #[Test]
    public function scopeEvaluatorAllowsWhenTechnicalScopesEmpty(): void
    {
        $auth = $this->makeAuth(true, new ArrayClass());
        $this->assertTrue(
            (new JSONWebTokenScopeEvaluator('api:read'))->evaluate($this->contextWithAuth($auth))
        );
    }

    #[Test]
    public function scopeEvaluatorAllowsMatchingScope(): void
    {
        $auth = $this->makeAuth(true, new ArrayClass(['api:read']));
        $this->assertTrue(
            (new JSONWebTokenScopeEvaluator('api:read'))->evaluate($this->contextWithAuth($auth))
        );
    }

    #[Test]
    public function scopeEvaluatorDeniesMismatchedScope(): void
    {
        $auth = $this->makeAuth(true, new ArrayClass(['api:write']));
        $this->assertFalse(
            (new JSONWebTokenScopeEvaluator('api:read'))->evaluate($this->contextWithAuth($auth))
        );
    }

    // --- Non-Bearer paths for JWT-specific evaluators ---

    #[Test]
    public function accessTimeEvaluatorAllowsNonBearerAuthentication(): void
    {
        $this->assertTrue(
            (new JSONWebTokenAccessTimeEvaluator())->evaluate($this->contextWithAuth($this->makeAuth(true)))
        );
    }

    #[Test]
    public function enabledEvaluatorDeniesNonBearerWithNoAuthenticatedUser(): void
    {
        // credential = null → authenticatedUser = null → false
        $this->assertFalse(
            (new JSONWebTokenEnabledEvaluator())->evaluate($this->contextWithAuth($this->makeAuth(true)))
        );
    }

    #[Test]
    public function versionEvaluatorAllowsNonBearerAuthentication(): void
    {
        $this->assertTrue(
            (new JSONWebTokenVersionEvaluator())->evaluate($this->contextWithAuth($this->makeAuth(true)))
        );
    }

    // --- bearer branches ---

    #[Test]
    public function aBearerWithoutATokenIsDeniedByEveryTokenEvaluator(): void
    {
        $context = $this->contextWithAuth($this->bearer(null));
        $this->assertFalse(new JSONWebTokenVersionEvaluator()->evaluate($context));
        $this->assertFalse(new JSONWebTokenRefreshTimeEvaluator()->evaluate($context));
        $this->assertFalse(new JSONWebTokenAccessTimeEvaluator()->evaluate($context));
    }

    #[Test]
    public function aRefreshTokenWithoutANotBeforeIsUsableAtOnce(): void
    {
        $this->assertTrue(new JSONWebTokenRefreshTimeEvaluator()->evaluate($this->contextWithAuth($this->bearer($this->payload()))));
    }

    #[Test]
    public function aRefreshTokenIsUsableOnceItsNotBeforeHasPassed(): void
    {
        $payload = $this->payload(notBefore: new Date()->addingTimeInterval(-60.0));
        $this->assertTrue(new JSONWebTokenRefreshTimeEvaluator()->evaluate($this->contextWithAuth($this->bearer($payload))));
    }

    #[Test]
    public function aRefreshTokenIsRefusedUntilItsNotBeforeArrives(): void
    {
        $payload = $this->payload(notBefore: new Date()->addingTimeInterval(3600.0));
        $this->assertFalse(new JSONWebTokenRefreshTimeEvaluator()->evaluate($this->contextWithAuth($this->bearer($payload))));
    }

    #[Test]
    public function aBearerWhoseSubjectCannotBeResolvedFailsTheVersionCheck(): void
    {
        $this->assertFalse(new JSONWebTokenVersionEvaluator()->evaluate($this->contextWithAuth($this->bearer($this->payload(version: 1), null))));
    }

    #[Test]
    public function aTokenIssuedForTheSubjectsCurrentVersionIsAccepted(): void
    {
        $bearer = $this->bearer($this->payload(version: 7), $this->userAtVersion(7));
        $this->assertTrue(new JSONWebTokenVersionEvaluator()->evaluate($this->contextWithAuth($bearer)));
    }

    #[Test]
    public function aTokenLeftBehindByAVersionBumpIsRefused(): void
    {
        $bearer = $this->bearer($this->payload(version: 6), $this->userAtVersion(7));
        $this->assertFalse(new JSONWebTokenVersionEvaluator()->evaluate($this->contextWithAuth($bearer)));
    }

    private function payload(?Date $notBefore = null, ?int $version = null): JSONWebTokenPayload
    {
        return new JSONWebTokenPayload(notBefore: $notBefore, version: $version);
    }

    private function bearer(?JSONWebTokenPayload $payload, ?Authorizable $user = null): BearerAuthentication
    {
        $bearer = new ReflectionClass(BearerAuthentication::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(Authentication::class, "environment")->setValue($bearer, new Dictionary());
        $token = $payload === null ? null : new JSONWebToken(new JSONWebTokenHeader(), $payload);
        new ReflectionProperty(BearerAuthentication::class, "isTokenResolved")->setValue($bearer, true);
        new ReflectionProperty(BearerAuthentication::class, "token")->setRawValue($bearer, $token);
        new ReflectionProperty(Authentication::class, "isAuthenticatedUserResolved")->setValue($bearer, true);
        new ReflectionProperty(Authentication::class, "authenticatedUser")->setRawValue($bearer, $user);
        return $bearer;
    }

    private function userAtVersion(int $version): Authorizable
    {
        return new class ($version) implements Authorizable {
            public function __construct(private readonly int $tokenVersion)
            {
            }

            public string $username { get => "ada"; }
            public ?string $password { get => null; }
            public bool $isEnabled { get => true; }
            public int $refreshTokenVersion { get => $this->tokenVersion; set {} }
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
