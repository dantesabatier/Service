<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;
use Sabatier\Service\AuthorizableRole;
use Sabatier\Service\Authorization;
use Sabatier\Service\AuthorizationCache;
use Sabatier\Service\AuthorizationResolver;
use Sabatier\Service\AuthorizationScope;
use Sabatier\Service\AuthorizationService;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\Authorizable;
use Sabatier\Service\CachedAuthorization;

final class AuthorizationServiceTest extends TestCase
{
    private function makeUser(): Authorizable
    {
        return new class implements Authorizable {
            public string $username { get => 'user'; }
            public ?string $password { get => null; }
            public bool $isEnabled { get => true; }
            public int $refreshTokenVersion { get => 1; set {} }
            public Set $roles { get => new Set(); }
            public function isEqual(mixed $other): bool { return $this === $other; }
            public static function defaultRepresentation(): Dictionary { return new Dictionary(); }
        };
    }

    private function makeAuth(string $name, AuthorizationType $type, AuthorizationScope $scope = AuthorizationScope::all): Authorization
    {
        return new CachedAuthorization($name, $type, $scope);
    }

    /** @param ArrayClass<Authorization>|null $authorizations */
    private function makeCache(?ArrayClass $authorizations): AuthorizationCache
    {
        return new class($authorizations) implements AuthorizationCache {
            /** @var ArrayClass<Authorization>|null */
            private ?ArrayClass $stored;
            /** @var ArrayClass<Authorization>|null */
            public ?ArrayClass $captured = null;
            public bool $invalidated = false;

            public function __construct(?ArrayClass $initial) {
                $this->stored = $initial;
            }

            public function getAuthorizableAuthorizations(Authorizable $authorizable): ?ArrayClass {
                return $this->stored;
            }

            public function setAuthorizableAuthorizations(Authorizable $authorizable, ArrayClass $authorizations): void {
                $this->captured = $authorizations;
                $this->stored = $authorizations;
            }

            public function invalidateAuthorizable(Authorizable $authorizable): void {
                $this->invalidated = true;
            }
        };
    }

    private function makeResolver(): AuthorizationResolver
    {
        return (new ReflectionClass(AuthorizationResolver::class))->newInstanceWithoutConstructor();
    }

    private function makeContext(): ManagedObjectContext
    {
        return (new ReflectionClass(ManagedObjectContext::class))->newInstanceWithoutConstructor();
    }

    /** @param string[] $scopes */
    private function makeScopes(string ...$scopes): ArrayClass
    {
        return new ArrayClass($scopes);
    }

    private function makeService(AuthorizationCache $inRequest, ?AuthorizationCache $persistent = null): AuthorizationService
    {
        return new AuthorizationService($this->makeResolver(), $inRequest, $persistent);
    }

    // --- Token scope fast-path ---

    #[Test]
    public function tokenScopeExactMatchGrantsAccessWithoutCacheLookup(): void
    {
        $service = $this->makeService($this->makeCache(null));
        $result = $service->isAuthorized(
            $this->makeUser(), 'posts', AuthorizationType::read,
            $this->makeScopes('posts:read'), $this->makeContext()
        );
        $this->assertTrue($result);
    }

    #[Test]
    public function tokenScopeAnyGrantsAccessForAnyAction(): void
    {
        $service = $this->makeService($this->makeCache(null));
        $result = $service->isAuthorized(
            $this->makeUser(), 'posts', AuthorizationType::delete,
            $this->makeScopes('posts:any'), $this->makeContext()
        );
        $this->assertTrue($result);
    }

    #[Test]
    public function tokenScopeWrongResourceDoesNotGrantAccess(): void
    {
        $inRequest = $this->makeCache(new ArrayClass([]));
        $service = $this->makeService($inRequest);
        $result = $service->isAuthorized(
            $this->makeUser(), 'posts', AuthorizationType::read,
            $this->makeScopes('comments:read'), $this->makeContext()
        );
        $this->assertFalse($result);
    }

    // --- In-request cache ---

    #[Test]
    public function inRequestCacheHitGrantsAccessWhenAuthorizationMatches(): void
    {
        $inRequest = $this->makeCache(new ArrayClass([$this->makeAuth('posts', AuthorizationType::read)]));
        $result = $this->makeService($inRequest)->isAuthorized(
            $this->makeUser(), 'posts', AuthorizationType::read,
            $this->makeScopes(), $this->makeContext()
        );
        $this->assertTrue($result);
    }

    #[Test]
    public function inRequestCacheHitDeniesAccessWhenAuthorizationDoesNotMatch(): void
    {
        $inRequest = $this->makeCache(new ArrayClass([$this->makeAuth('posts', AuthorizationType::read)]));
        $result = $this->makeService($inRequest)->isAuthorized(
            $this->makeUser(), 'posts', AuthorizationType::delete,
            $this->makeScopes(), $this->makeContext()
        );
        $this->assertFalse($result);
    }

    // --- Persistent cache ---

    #[Test]
    public function persistentCacheHitIsPromotedToInRequestCache(): void
    {
        $authorizations = new ArrayClass([$this->makeAuth('posts', AuthorizationType::read)]);
        $inRequest = $this->makeCache(null);
        $persistent = $this->makeCache($authorizations);
        $this->makeService($inRequest, $persistent)->isAuthorized(
            $this->makeUser(), 'posts', AuthorizationType::read,
            $this->makeScopes(), $this->makeContext()
        );
        $this->assertNotNull($inRequest->captured);
    }

    #[Test]
    public function persistentCacheHitGrantsAccessWithoutResolver(): void
    {
        $authorizations = new ArrayClass([$this->makeAuth('posts', AuthorizationType::read)]);
        $result = $this->makeService($this->makeCache(null), $this->makeCache($authorizations))->isAuthorized(
            $this->makeUser(), 'posts', AuthorizationType::read,
            $this->makeScopes(), $this->makeContext()
        );
        $this->assertTrue($result);
    }

    // --- Authorization matching ---

    #[Test]
    public function authorizationTypeAnyGrantsAccessForSpecificAction(): void
    {
        $inRequest = $this->makeCache(new ArrayClass([$this->makeAuth('posts', AuthorizationType::any)]));
        $result = $this->makeService($inRequest)->isAuthorized(
            $this->makeUser(), 'posts', AuthorizationType::update,
            $this->makeScopes(), $this->makeContext()
        );
        $this->assertTrue($result);
    }

    #[Test]
    public function authorizationMatchIsCaseInsensitiveOnResourceName(): void
    {
        $inRequest = $this->makeCache(new ArrayClass([$this->makeAuth('Posts', AuthorizationType::read)]));
        $result = $this->makeService($inRequest)->isAuthorized(
            $this->makeUser(), 'posts', AuthorizationType::read,
            $this->makeScopes(), $this->makeContext()
        );
        $this->assertTrue($result);
    }

    #[Test]
    public function wrongResourceNameDeniesAccess(): void
    {
        $inRequest = $this->makeCache(new ArrayClass([$this->makeAuth('comments', AuthorizationType::read)]));
        $result = $this->makeService($inRequest)->isAuthorized(
            $this->makeUser(), 'posts', AuthorizationType::read,
            $this->makeScopes(), $this->makeContext()
        );
        $this->assertFalse($result);
    }

    // --- invalidateAuthorizable ---

    #[Test]
    public function invalidateAuthorizableCallsBothCaches(): void
    {
        $inRequest = $this->makeCache(null);
        $persistent = $this->makeCache(null);
        $this->makeService($inRequest, $persistent)->invalidateAuthorizable($this->makeUser());
        $this->assertTrue($inRequest->invalidated);
        $this->assertTrue($persistent->invalidated);
    }

    #[Test]
    public function invalidateAuthorizableWithoutPersistentCacheDoesNotThrow(): void
    {
        $inRequest = $this->makeCache(null);
        $this->makeService($inRequest)->invalidateAuthorizable($this->makeUser());
        $this->assertTrue($inRequest->invalidated);
    }
}
