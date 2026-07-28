<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;
use Sabatier\Service\Authorizable;
use Sabatier\Service\AuthorizableRole;
use Sabatier\Service\AuthorizationContext;
use Sabatier\Service\FieldLevelSecurityPolicy;
use Sabatier\Service\Readable;

// --- Fixtures ---

#[Readable(where: 'day == $TODAY')]
class TodayGuardedResourceFixture extends ManagedObject
{
}

#[Readable]
class BareReadableResourceFixture extends ManagedObject
{
}

class UnguardedResourceFixture extends ManagedObject
{
}

#[Readable(["Finance"])]
class RoleGuardedResourceFixture extends ManagedObject
{
}

#[Readable(["Finance"], where: 'day == $TODAY')]
class RoleAndConditionGuardedResourceFixture extends ManagedObject
{
}

// --- Tests ---

final class ResourceReadPredicateTest extends TestCase
{
    private function makePolicy(bool $securityEnabled, string ...$roleNames): FieldLevelSecurityPolicy
    {
        $user = $roleNames === [] ? null : $this->makeUser(...$roleNames);
        return new FieldLevelSecurityPolicy(new AuthorizationContext($user, new ArrayClass(), $securityEnabled));
    }

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
            public function isEqual(mixed $other): bool { return $this === $other; }
            public static function defaultRepresentation(): Dictionary { return new Dictionary(); }
        };
    }

    #[Test]
    public function resolvesWherePredicateFromClassReadable(): void
    {
        $predicate = $this->makePolicy(true)->resourceReadPredicate(TodayGuardedResourceFixture::class);
        $this->assertNotNull($predicate);
        $this->assertSame('day = $TODAY', $predicate->predicateFormat);
    }

    #[Test]
    public function returnsNullWhenReadableHasNoWhere(): void
    {
        $this->assertNull($this->makePolicy(true)->resourceReadPredicate(BareReadableResourceFixture::class));
    }

    #[Test]
    public function returnsNullWhenClassCarriesNoReadable(): void
    {
        $this->assertNull($this->makePolicy(true)->resourceReadPredicate(UnguardedResourceFixture::class));
    }

    #[Test]
    public function returnsNullWhenSecurityDisabled(): void
    {
        $this->assertNull($this->makePolicy(false)->resourceReadPredicate(TodayGuardedResourceFixture::class));
    }

    #[Test]
    public function returnsNullWhenSubjectHoldsTheRequiredRole(): void
    {
        $this->assertNull($this->makePolicy(true, "Finance")->resourceReadPredicate(RoleGuardedResourceFixture::class), "a role the rule admits has nothing to narrow");
    }

    #[Test]
    public function narrowsToNoRowsWhenSubjectLacksTheRequiredRole(): void
    {
        $predicate = $this->makePolicy(true, "Sales")->resourceReadPredicate(RoleGuardedResourceFixture::class);
        $this->assertNotNull($predicate);
        $this->assertSame("FALSEPREDICATE", $predicate->predicateFormat, "a role the rule excludes reads no rows at all");
    }

    #[Test]
    public function narrowsToNoRowsWhenSubjectIsUnauthenticated(): void
    {
        $predicate = $this->makePolicy(true)->resourceReadPredicate(RoleGuardedResourceFixture::class);
        $this->assertNotNull($predicate);
        $this->assertSame("FALSEPREDICATE", $predicate->predicateFormat, "an unauthenticated subject holds no roles and so is excluded");
    }

    #[Test]
    public function excludedRoleTakesPrecedenceOverTheCondition(): void
    {
        $predicate = $this->makePolicy(true, "Sales")->resourceReadPredicate(RoleAndConditionGuardedResourceFixture::class);
        $this->assertNotNull($predicate);
        $this->assertSame("FALSEPREDICATE", $predicate->predicateFormat, "the condition never widens what the roles already denied");
    }

    #[Test]
    public function admittedRoleStillHonoursTheCondition(): void
    {
        $predicate = $this->makePolicy(true, "Finance")->resourceReadPredicate(RoleAndConditionGuardedResourceFixture::class);
        $this->assertNotNull($predicate);
        $this->assertSame('day = $TODAY', $predicate->predicateFormat, "an admitted role is still narrowed by the rule's condition");
    }
}
