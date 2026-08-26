<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;
use Sabatier\Service\Authorizable;
use Sabatier\Service\AuthorizableRole;
use Sabatier\Service\AuthorizationContext;
use Sabatier\Service\AuthorizationScope;
use Sabatier\Service\FieldLevelSecurityPolicy;
use Sabatier\Service\ForbiddenException;
use Sabatier\Service\Owner;
use Sabatier\Service\Writable;

// --- Fixtures ---

class UnguardedWriteFixture extends ManagedObject
{
    #[Owner]
    public ?Authorizable $createdBy = null;
}

#[Writable(["Finance"])]
class RoleGuardedWriteFixture extends ManagedObject
{
    #[Owner]
    public ?Authorizable $createdBy = null;
}

#[Writable(["Finance"], AuthorizationScope::own)]
class OwnScopedWriteFixture extends ManagedObject
{
    #[Owner]
    public ?Authorizable $createdBy = null;
}

#[Writable(["Finance"], AuthorizationScope::own)]
class OwnScopedWriteWithoutOwnerFieldFixture extends ManagedObject
{
}

// --- Tests ---

/**
 * Covers the resource-level write rule declared by a class-level `#[Writable]`: role membership
 * and the `own` scope. The scope is enforced against the row's `#[Owner]` field independently of
 * the request's `own` authorization scope, and denies a row whose ownership cannot be established
 * — a rule that explicitly demands ownership must not pass an unowned row.
 */
final class ResourceWriteAccessTest extends TestCase
{
    private function makeRole(string $name): AuthorizableRole
    {
        return new class($name) implements AuthorizableRole {
            public function __construct(private readonly string $roleName) {}
            public string $name { get => $this->roleName; }
            public Set $authorizations { get => new Set(); }
        };
    }

    private function makeUser(string ...$roleNames): Authorizable
    {
        $roles = new Set(array_map(fn(string $name): AuthorizableRole => $this->makeRole($name), $roleNames));
        return new class($roles) implements Authorizable {
            public function __construct(private readonly Set $userRoles) {}
            public string $username { get => "user"; }
            public ?string $password { get => null; }
            public bool $isEnabled { get => true; }
            public int $refreshTokenVersion { get => 1; set {} }
            public Set $roles { get => $this->userRoles; }
            #[Override]
            public function isEqual(mixed $other): bool { return $this === $other; }
            #[Override]
            public static function defaultRepresentation(): Dictionary { return new Dictionary(); }
        };
    }

    private function makePolicy(?Authorizable $user, bool $securityEnabled = true): FieldLevelSecurityPolicy
    {
        return new FieldLevelSecurityPolicy(new AuthorizationContext($user, new ArrayClass(), $securityEnabled));
    }

    /**
     * Builds a fixture with the minimal CoreData stubs so `valueForKey()` falls through to the PHP
     * property and `entity->name` is available for the denial message.
     *
     * @param class-string<ManagedObject> $fixtureClass
     */
    private function makeResource(string $fixtureClass, ?Authorizable $owner = null): ManagedObject
    {
        $resource = new ReflectionClass($fixtureClass)->newInstanceWithoutConstructor();
        $entity = new ReflectionClass(EntityDescription::class)->newInstanceWithoutConstructor();
        $entity->name = "Fixture";
        $context = new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
        new ReflectionClass(ManagedObject::class)->getProperty("entity")->setValue($resource, $entity);
        new ReflectionClass(ManagedObject::class)->getProperty("managedObjectContext")->setValue($resource, $context);
        if (property_exists($resource, "createdBy")) {
            $resource->createdBy = $owner;
        }
        return $resource;
    }

    #[Test]
    public function classWithoutWritableIsUnrestricted(): void
    {
        $this->expectNotToPerformAssertions();
        $this->makePolicy($this->makeUser("Sales"))->enforceResourceAccess($this->makeResource(UnguardedWriteFixture::class));
    }

    #[Test]
    public function securityDisabledSkipsEnforcement(): void
    {
        $this->expectNotToPerformAssertions();
        $this->makePolicy($this->makeUser("Sales"), false)->enforceResourceAccess($this->makeResource(RoleGuardedWriteFixture::class));
    }

    #[Test]
    public function admittedRolePassesWithoutScope(): void
    {
        $this->expectNotToPerformAssertions();
        $this->makePolicy($this->makeUser("Finance"))->enforceResourceAccess($this->makeResource(RoleGuardedWriteFixture::class));
    }

    #[Test]
    public function excludedRoleIsDenied(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->makePolicy($this->makeUser("Sales"))->enforceResourceAccess($this->makeResource(RoleGuardedWriteFixture::class));
    }

    // --- scope ---

    #[Test]
    public function ownScopePassesForTheOwnRow(): void
    {
        $this->expectNotToPerformAssertions();
        $user = $this->makeUser("Finance");
        $this->makePolicy($user)->enforceResourceAccess($this->makeResource(OwnScopedWriteFixture::class, $user));
    }

    #[Test]
    public function ownScopeDeniesAnotherSubjectsRow(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->makePolicy($this->makeUser("Finance"))->enforceResourceAccess($this->makeResource(OwnScopedWriteFixture::class, $this->makeUser("Finance")));
    }

    #[Test]
    public function ownScopeDeniesAnUnownedRow(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->makePolicy($this->makeUser("Finance"))->enforceResourceAccess($this->makeResource(OwnScopedWriteFixture::class));
    }

    #[Test]
    public function ownScopeDeniesWhenTheClassDeclaresNoOwnerField(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->makePolicy($this->makeUser("Finance"))->enforceResourceAccess($this->makeResource(OwnScopedWriteWithoutOwnerFieldFixture::class));
    }
}
