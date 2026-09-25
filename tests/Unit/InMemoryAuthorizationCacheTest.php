<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;
use Sabatier\Service\AuthorizationScope;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\Authorizable;
use Sabatier\Service\CachedAuthorization;
use Sabatier\Service\InMemoryAuthorizationCache;

final class InMemoryAuthorizationCacheTest extends TestCase
{
    /** @var list<Authorizable> */
    private array $usersToClean = [];

    #[Override]
    protected function tearDown(): void
    {
        $cache = new InMemoryAuthorizationCache();
        foreach ($this->usersToClean as $user) {
            $cache->invalidateAuthorizable($user);
        }
    }

    private function makeUser(string $username): Authorizable
    {
        $user = new class($username) implements Authorizable {
            public function __construct(private readonly string $_username) {}
            public string $username { get => $this->_username; }
            public ?string $password { get => null; }
            public bool $isEnabled { get => true; }
            public int $refreshTokenVersion { get => 1; set {} }
            public Set $roles { get => new Set(); }
            #[Override]
            public function isEqual(mixed $other): bool { return $this === $other; }
            #[Override]
            public static function defaultRepresentation(): Dictionary { return new Dictionary(); }
        };
        $this->usersToClean[] = $user;
        return $user;
    }

    private function makeAuthorizations(string ...$names): ArrayClass
    {
        return new ArrayClass(array_map(
            fn(string $n) => new CachedAuthorization($n, AuthorizationType::read, AuthorizationScope::all),
            $names
        ));
    }

    // --- Cache miss ---

    #[Test]
    public function returnsNullForUnknownUser(): void
    {
        $cache = new InMemoryAuthorizationCache();
        $this->assertNull($cache->getAuthorizableAuthorizations($this->makeUser("unknown-" . uniqid())));
    }

    // --- Set then get ---

    #[Test]
    public function getReturnsAuthorizationsAfterSet(): void
    {
        $cache = new InMemoryAuthorizationCache();
        $user = $this->makeUser("alice");
        $authorizations = $this->makeAuthorizations("posts", "comments");
        $cache->setAuthorizableAuthorizations($user, $authorizations);
        $this->assertSame($authorizations, $cache->getAuthorizableAuthorizations($user));
    }

    // --- Invalidate ---

    #[Test]
    public function getReturnsNullAfterInvalidate(): void
    {
        $cache = new InMemoryAuthorizationCache();
        $user = $this->makeUser("bob");
        $cache->setAuthorizableAuthorizations($user, $this->makeAuthorizations("posts"));
        $cache->invalidateAuthorizable($user);
        $this->assertNull($cache->getAuthorizableAuthorizations($user));
    }

    #[Test]
    public function invalidatingUnknownUserDoesNotThrow(): void
    {
        $cache = new InMemoryAuthorizationCache();
        $cache->invalidateAuthorizable($this->makeUser("nobody-" . uniqid()));
        $this->addToAssertionCount(1);
    }

    // --- Isolation between users ---

    #[Test]
    public function usersAreKeyedByUsername(): void
    {
        $cache = new InMemoryAuthorizationCache();
        $alice = $this->makeUser("alice-iso");
        $bob = $this->makeUser("bob-iso");
        $aliceAuth = $this->makeAuthorizations("posts");
        $bobAuth = $this->makeAuthorizations("users");
        $cache->setAuthorizableAuthorizations($alice, $aliceAuth);
        $cache->setAuthorizableAuthorizations($bob, $bobAuth);
        $this->assertSame($aliceAuth, $cache->getAuthorizableAuthorizations($alice));
        $this->assertSame($bobAuth, $cache->getAuthorizableAuthorizations($bob));
    }

    #[Test]
    public function invalidatingOneUserDoesNotAffectAnother(): void
    {
        $cache = new InMemoryAuthorizationCache();
        $alice = $this->makeUser("alice-inv");
        $bob = $this->makeUser("bob-inv");
        $bobAuth = $this->makeAuthorizations("users");
        $cache->setAuthorizableAuthorizations($alice, $this->makeAuthorizations("posts"));
        $cache->setAuthorizableAuthorizations($bob, $bobAuth);
        $cache->invalidateAuthorizable($alice);
        $this->assertNull($cache->getAuthorizableAuthorizations($alice));
        $this->assertSame($bobAuth, $cache->getAuthorizableAuthorizations($bob));
    }

    // --- Overwrite ---

    #[Test]
    public function setOverwritesExistingEntry(): void
    {
        $cache = new InMemoryAuthorizationCache();
        $user = $this->makeUser("carol");
        $cache->setAuthorizableAuthorizations($user, $this->makeAuthorizations("posts"));
        $updated = $this->makeAuthorizations("posts", "users", "comments");
        $cache->setAuthorizableAuthorizations($user, $updated);
        $this->assertSame($updated, $cache->getAuthorizableAuthorizations($user));
    }

    // --- Shared static storage across instances ---

    #[Test]
    public function twoInstancesShareTheSameStorage(): void
    {
        $user = $this->makeUser("dave");
        $authorizations = $this->makeAuthorizations("reports");
        new InMemoryAuthorizationCache()->setAuthorizableAuthorizations($user, $authorizations);
        $this->assertSame($authorizations, new InMemoryAuthorizationCache()->getAuthorizableAuthorizations($user));
    }
}
