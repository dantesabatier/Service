<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Exception;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Set;
use Sabatier\Service\Authentication;
use Sabatier\Service\AuthenticationContext;
use Sabatier\Service\AuthenticationService;
use Sabatier\Service\Authorizable;
use Sabatier\Service\AuthorizationContext;
use Sabatier\Service\AuthorizationHeader;
use Sabatier\Service\AuthorizationResolver;
use Sabatier\Service\AuthorizationScope;
use Sabatier\Service\AuthorizationService;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\BasicAuthentication;
use Sabatier\Service\BearerAuthentication;
use Sabatier\Service\CachedAuthorization;
use Sabatier\Service\FieldLevelSecurityPolicy;
use Sabatier\Service\InMemoryAuthorizationCache;
use Sabatier\Service\JSONWebTokenCoderStrategyRegistrar;
use Sabatier\Service\JSONWebTokenPayload;
use Sabatier\Service\JSONWebTokenService;
use const Sabatier\Service\JWTPrivateKey;

final class AuthenticationAuthorizationScopesTest extends TestCase
{
    private const string key = "a-secret-long-enough-to-sign-a-token";

    /** @throws ReflectionException */
    #[Test]
    public function aBasicUserCarriesTheScopesItsRolesGrant(): void
    {
        $user = $this->user();
        $this->grant($user, new CachedAuthorization("Order", AuthorizationType::read, AuthorizationScope::own));
        $authentication = $this->withUser(new BasicAuthentication($this->context("Basic " . base64_encode("ada:secret"), $this->service()), new Dictionary()), $user);
        $this->assertSame(["Order:read:own"], $authentication->authorizationScopes->array);
        $this->assertTrue(new FieldLevelSecurityPolicy(new AuthorizationContext($user, $authentication->authorizationScopes, true))->hasOwnScopeFor("Order", AuthorizationType::read));
    }

    /** @throws Exception */
    #[Test]
    public function aBearerUserCarriesTheScopesItsTokenCarries(): void
    {
        $user = $this->user();
        $this->grant($user, new CachedAuthorization("Order", AuthorizationType::read, AuthorizationScope::own));
        $token = new JSONWebTokenService(self::key)->encode(new JSONWebTokenPayload(subject: "ada", authorizationScopes: ["Order:read:all"])->rawValue);
        $authentication = $this->withUser(new BearerAuthentication($this->context("Bearer $token", $this->service()), new Dictionary([JWTPrivateKey => self::key])), $user);
        $this->assertSame(["Order:read:all"], $authentication->authorizationScopes->array);
    }

    /** @throws ReflectionException */
    #[Test]
    public function withoutAUserThereAreNoScopes(): void
    {
        $this->assertTrue($this->withUser(new BasicAuthentication($this->context("Basic", $this->service()), new Dictionary()), null)->authorizationScopes->isEmpty);
    }

    /** @throws ReflectionException */
    #[Test]
    public function withoutAnAuthorizationServiceThereAreNoScopes(): void
    {
        $user = $this->user();
        $this->grant($user, new CachedAuthorization("Order", AuthorizationType::read, AuthorizationScope::own));
        $this->assertTrue($this->withUser(new BasicAuthentication($this->context("Basic " . base64_encode("ada:secret"), null), new Dictionary()), $user)->authorizationScopes->isEmpty);
    }

    #[Override]
    public static function setUpBeforeClass(): void
    {
        JSONWebTokenCoderStrategyRegistrar::register();
    }

    #[Override]
    protected function tearDown(): void
    {
        new InMemoryAuthorizationCache()->invalidateAll();
        parent::tearDown();
    }

    private function grant(Authorizable $user, CachedAuthorization ...$authorizations): void
    {
        new InMemoryAuthorizationCache()->setAuthorizableAuthorizations($user, new ArrayClass($authorizations));
    }

    /** @throws ReflectionException */
    private function service(): AuthorizationService
    {
        return new AuthorizationService(new ReflectionClass(AuthorizationResolver::class)->newInstanceWithoutConstructor(), new InMemoryAuthorizationCache());
    }

    /** @throws ReflectionException */
    private function context(string $header, ?AuthorizationService $authorizationService): AuthenticationContext
    {
        return new AuthenticationContext(new AuthorizationHeader($header), HTTPRequestMethod::get, new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor(), null, new ReflectionClass(AuthenticationService::class)->newInstanceWithoutConstructor(), $authorizationService);
    }

    /**
     * @template T of Authentication
     * @param T $authentication
     * @return T
     */
    private function withUser(Authentication $authentication, ?Authorizable $user): Authentication
    {
        new ReflectionProperty(Authentication::class, "isAuthenticatedUserResolved")->setValue($authentication, true);
        new ReflectionProperty(Authentication::class, "authenticatedUser")->setRawValue($authentication, $user);
        return $authentication;
    }

    private function user(): Authorizable
    {
        return new class implements Authorizable {
            public string $username { get => "ada"; }
            public ?string $password { get => "secret"; }
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
