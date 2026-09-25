<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Set;
use Sabatier\Service\AuthorizationScope;
use Sabatier\Service\Readable;
use Sabatier\Service\ResourceRule;
use Sabatier\Service\Writable;

// --- Fixtures ---

#[Readable(["Finance"], where: "status != %@", arguments: ["archived"])]
#[Writable(["Admin"], AuthorizationScope::own)]
class ResourceRuleGuardedFixture extends ManagedObject
{
}

class ResourceRuleUnguardedFixture extends ManagedObject
{
}

#[Readable]
class ResourceRuleOpenFixture extends ManagedObject
{
}

// --- Tests ---

final class ResourceRuleTest extends TestCase
{
    // --- allowsRoles ---

    #[Test]
    public function allowsAnyRoleWhenRoleSetIsEmpty(): void
    {
        $rule = new ResourceRule(new Set(), AuthorizationScope::all);
        $this->assertTrue($rule->allowsRoles(new Set()));
        $this->assertTrue($rule->allowsRoles(new Set(["Guest"])));
    }

    #[Test]
    public function allowsRolesWhenUserSharesARole(): void
    {
        $rule = new ResourceRule(new Set(["Admin", "Manager"]), AuthorizationScope::all);
        $this->assertTrue($rule->allowsRoles(new Set(["Manager", "User"])));
    }

    #[Test]
    public function deniesRolesWhenSetsAreDisjoint(): void
    {
        $rule = new ResourceRule(new Set(["Admin"]), AuthorizationScope::all);
        $this->assertFalse($rule->allowsRoles(new Set(["User", "Guest"])));
    }

    // --- requiresOwner ---

    #[Test]
    public function requiresOwnerTrueForOwnScope(): void
    {
        $this->assertTrue(new ResourceRule(new Set(), AuthorizationScope::own)->requiresOwner);
    }

    #[Test]
    public function requiresOwnerFalseForAllScope(): void
    {
        $this->assertFalse(new ResourceRule(new Set(), AuthorizationScope::all)->requiresOwner);
    }

    // --- resolve ---

    /** @throws Exception */
    #[Test]
    public function resolveReturnsNullWhenClassCarriesNoAttribute(): void
    {
        $this->assertNull(ResourceRule::resolve(ResourceRuleUnguardedFixture::class, Readable::class));
        $this->assertNull(ResourceRule::resolve(ResourceRuleUnguardedFixture::class, Writable::class));
    }

    /** @throws Exception */
    #[Test]
    public function resolveReadsRolesWhereAndArgumentsFromReadable(): void
    {
        $rule = ResourceRule::resolve(ResourceRuleGuardedFixture::class, Readable::class);
        $this->assertNotNull($rule);
        $this->assertTrue($rule->allowsRoles(new Set(["Finance"])));
        $this->assertFalse($rule->allowsRoles(new Set(["User"])));
        $this->assertSame("status != %@", $rule->where);
        $this->assertSame(["archived"], $rule->arguments);
        $this->assertSame(AuthorizationScope::all, $rule->scope);
        $this->assertFalse($rule->requiresOwner);
    }

    /** @throws Exception */
    #[Test]
    public function resolveDiscriminatesBetweenReadableAndWritableOnSameClass(): void
    {
        $writable = ResourceRule::resolve(ResourceRuleGuardedFixture::class, Writable::class);
        $this->assertNotNull($writable);
        $this->assertTrue($writable->allowsRoles(new Set(["Admin"])));
        $this->assertFalse($writable->allowsRoles(new Set(["Finance"])));
        $this->assertSame(AuthorizationScope::own, $writable->scope);
        $this->assertTrue($writable->requiresOwner);
        $this->assertNull($writable->where);
        $this->assertSame([], $writable->arguments);
    }

    /** @throws Exception */
    #[Test]
    public function resolveDefaultsToEmptyRolesAllScopeForBareAttribute(): void
    {
        $rule = ResourceRule::resolve(ResourceRuleOpenFixture::class, Readable::class);
        $this->assertNotNull($rule);
        $this->assertTrue($rule->allowsRoles(new Set()));
        $this->assertSame(AuthorizationScope::all, $rule->scope);
        $this->assertNull($rule->where);
    }

    /** @throws Exception */
    #[Test]
    public function resolveReturnsCachedInstanceOnSecondCall(): void
    {
        $first = ResourceRule::resolve(ResourceRuleGuardedFixture::class, Readable::class);
        $second = ResourceRule::resolve(ResourceRuleGuardedFixture::class, Readable::class);
        $this->assertSame($first, $second);
    }
}
