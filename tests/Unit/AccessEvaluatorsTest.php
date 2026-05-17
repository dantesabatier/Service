<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Service\AccessEvaluationContext;
use Sabatier\Service\Authentication;
use Sabatier\Service\AuthenticationEvaluator;
use Sabatier\Service\AuthenticationScheme;
use Sabatier\Service\JSONWebTokenAccessTimeEvaluator;
use Sabatier\Service\JSONWebTokenEnabledEvaluator;
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
}
