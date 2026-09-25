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
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;
use Sabatier\Service\AccessConditionResolver;
use Sabatier\Service\AuthorizableRole;
use Sabatier\Service\AuthorizationScope;
use Sabatier\Service\Authorizable;
use Sabatier\Service\FieldRule;
use Sabatier\Service\FieldSecurityFilter;
use Sabatier\Service\Owner;
use Sabatier\Service\Readable;
use Sabatier\Service\Writable;

// --- Fixtures ---

class OpenEntityFixture extends ManagedObject
{
    #[Readable]
    public string $name = "";
    #[Readable]
    public float $price = 0.0;
    #[Writable]
    public string $summary = "";
}

class RoleRestrictedEntityFixture extends ManagedObject
{
    #[Readable]
    public string $name = "";
    #[Readable(["Admin"])]
    public float $price = 0.0;
    #[Readable(["Admin", "Manager"])]
    public string $internalCode = "";
    #[Writable(["Admin"])]
    public string $adminNote = "";
}

class PlainEntityFixture extends ManagedObject
{
    public string $name = "";
    public float $price = 0.0;
}

class OwnedEntityFixture extends ManagedObject
{
    #[Owner]
    public ?Authorizable $createdBy = null;

    #[Readable(["Admin"], AuthorizationScope::own)]
    public string $secret = "";
}

class ConditionEntityFixture extends ManagedObject
{
    public string $status = "";

    #[Readable(where: "status == %@", arguments: ["published"])]
    public string $body = "";
}

// --- Tests ---

final class FieldSecurityFilterTest extends TestCase
{
    private function makeRole(string $name): AuthorizableRole
    {
        return new class($name) implements AuthorizableRole {
            public function __construct(private readonly string $_name) {}
            public string $name { get => $this->_name; }
            public Set $authorizations { get => new Set(); }
        };
    }

    private function makeUser(string ...$roleNames): Authorizable
    {
        $roles = new Set(array_map(fn(string $n) => $this->makeRole($n), $roleNames));
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

    private function makeResource(string $fixtureClass): ManagedObject
    {
        return new ReflectionClass($fixtureClass)->newInstanceWithoutConstructor();
    }

    /**
     * Creates an OwnedEntityFixture with the minimal CoreData stubs needed so that
     * ManagedObject::valueForKey() can fall through to ObjectClass::valueForKey(),
     * which reads the PHP property directly.
     */
    private function makeOwnedResource(?Authorizable $owner): OwnedEntityFixture
    {
        /** @var OwnedEntityFixture $resource */
        $resource = new ReflectionClass(OwnedEntityFixture::class)->newInstanceWithoutConstructor();
        $entity = new ReflectionClass(EntityDescription::class)->newInstanceWithoutConstructor();
        $context = new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
        new ReflectionClass(ManagedObject::class)->getProperty("entity")->setValue($resource, $entity);
        new ReflectionClass(ManagedObject::class)->getProperty("managedObjectContext")->setValue($resource, $context);
        $resource->createdBy = $owner;
        return $resource;
    }

    // --- FieldRule ---

    #[Test]
    public function allowsRolesWhenRoleSetIsEmpty(): void
    {
        $rule = new FieldRule(new Set(), AuthorizationScope::all);
        $this->assertTrue($rule->allowsRoles(new Set()));
        $this->assertTrue($rule->allowsRoles(new Set(["Admin"])));
    }

    #[Test]
    public function allowsRolesWhenUserHasMatchingRole(): void
    {
        $rule = new FieldRule(new Set(["Admin"]), AuthorizationScope::all);
        $this->assertTrue($rule->allowsRoles(new Set(["Admin"])));
    }

    #[Test]
    public function allowsRolesReturnsFalseWhenNoMatch(): void
    {
        $rule = new FieldRule(new Set(["Admin"]), AuthorizationScope::all);
        $this->assertFalse($rule->allowsRoles(new Set(["User", "Guest"])));
    }

    #[Test]
    public function requiresOwnerTrueForOwnScope(): void
    {
        $this->assertTrue(new FieldRule(new Set(), AuthorizationScope::own)->requiresOwner);
    }

    #[Test]
    public function requiresOwnerFalseForAllScope(): void
    {
        $this->assertFalse(new FieldRule(new Set(), AuthorizationScope::all)->requiresOwner);
    }

    // --- filterRead ---

    #[Test]
    public function filterReadPassesThroughWhenNoAttributes(): void
    {
        $data = new Dictionary(["name" => "Product", "price" => 9.99]);
        $result = new FieldSecurityFilter(
            $this->makeResource(PlainEntityFixture::class),
            $this->makeUser()
        )->filterRead($data);
        $this->assertSame("Product", $result["name"]);
        $this->assertSame(9.99, $result["price"]);
    }

    #[Test]
    public function filterReadKeepsAllFieldsWhenEmptyRolesAllowAll(): void
    {
        $data = new Dictionary(["name" => "Product", "price" => 9.99]);
        $result = new FieldSecurityFilter(
            $this->makeResource(OpenEntityFixture::class),
            $this->makeUser()
        )->filterRead($data);
        $this->assertSame("Product", $result["name"]);
        $this->assertSame(9.99, $result["price"]);
    }

    #[Test]
    public function filterReadRemovesFieldWhenUserLacksRole(): void
    {
        $data = new Dictionary(["name" => "Product", "price" => 9.99]);
        $result = new FieldSecurityFilter(
            $this->makeResource(RoleRestrictedEntityFixture::class),
            $this->makeUser()
        )->filterRead($data);
        $this->assertSame("Product", $result["name"]);
        $this->assertNull($result["price"]);
    }

    #[Test]
    public function filterReadKeepsFieldWhenUserHasRequiredRole(): void
    {
        $data = new Dictionary(["name" => "Product", "price" => 9.99]);
        $result = new FieldSecurityFilter(
            $this->makeResource(RoleRestrictedEntityFixture::class),
            $this->makeUser("Admin")
        )->filterRead($data);
        $this->assertSame("Product", $result["name"]);
        $this->assertSame(9.99, $result["price"]);
    }

    #[Test]
    public function filterReadKeepsFieldWhenUserHasOneOfMultipleAllowedRoles(): void
    {
        $data = new Dictionary(["name" => "P", "internalCode" => "SKU-1"]);
        $result = new FieldSecurityFilter(
            $this->makeResource(RoleRestrictedEntityFixture::class),
            $this->makeUser("Manager")
        )->filterRead($data);
        $this->assertSame("SKU-1", $result["internalCode"]);
    }

    #[Test]
    public function filterReadIgnoresFieldsNotPresentInData(): void
    {
        $data = new Dictionary(["name" => "Product"]); // price absent
        $result = new FieldSecurityFilter(
            $this->makeResource(RoleRestrictedEntityFixture::class),
            $this->makeUser()
        )->filterRead($data);
        $this->assertSame("Product", $result["name"]);
        $this->assertNull($result["price"]); // absent, not filtered
    }

    // --- filterWrite ---

    #[Test]
    public function filterWriteRemovesFieldWhenUserLacksRole(): void
    {
        $data = new Dictionary(["summary" => "text", "adminNote" => "secret"]);
        $result = new FieldSecurityFilter(
            $this->makeResource(RoleRestrictedEntityFixture::class),
            $this->makeUser()
        )->filterWrite($data);
        $this->assertNull($result["adminNote"]);
    }

    #[Test]
    public function filterWriteKeepsFieldWhenUserHasRole(): void
    {
        $data = new Dictionary(["summary" => "text", "adminNote" => "secret"]);
        $result = new FieldSecurityFilter(
            $this->makeResource(RoleRestrictedEntityFixture::class),
            $this->makeUser("Admin")
        )->filterWrite($data);
        $this->assertSame("secret", $result["adminNote"]);
    }

    // --- Own scope ---

    #[Test]
    public function filterReadRemovesOwnedFieldWhenOwnedByAnotherUser(): void
    {
        $result = new FieldSecurityFilter(
            $this->makeOwnedResource($this->makeUser("User")),
            $this->makeUser("User")
        )->filterRead(new Dictionary(["secret" => "classified"]));
        $this->assertNull($result["secret"]);
    }

    #[Test]
    public function filterReadKeepsOwnedFieldWhenResourceHasNoOwner(): void
    {
        $user = $this->makeUser("User");
        $result = new FieldSecurityFilter(
            $this->makeOwnedResource(null),
            $user
        )->filterRead(new Dictionary(["secret" => "classified"]));
        $this->assertSame("classified", $result["secret"]);
    }

    #[Test]
    public function filterReadKeepsOwnedFieldWhenIsOwner(): void
    {
        $user = $this->makeUser("User");
        $result = new FieldSecurityFilter(
            $this->makeOwnedResource($user),
            $user
        )->filterRead(new Dictionary(["secret" => "classified"]));
        $this->assertSame("classified", $result["secret"]);
    }

    // --- Attribute-based condition (where) ---

    private function makeConditionResource(string $status): ConditionEntityFixture
    {
        /** @var ConditionEntityFixture $resource */
        $resource = new ReflectionClass(ConditionEntityFixture::class)->newInstanceWithoutConstructor();
        $entity = new ReflectionClass(EntityDescription::class)->newInstanceWithoutConstructor();
        $context = new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
        new ReflectionClass(ManagedObject::class)->getProperty("entity")->setValue($resource, $entity);
        new ReflectionClass(ManagedObject::class)->getProperty("managedObjectContext")->setValue($resource, $context);
        $resource->status = $status;
        return $resource;
    }

    #[Test]
    public function filterReadKeepsFieldWhenResourceConditionHolds(): void
    {
        $user = $this->makeUser();
        $resolver = new AccessConditionResolver();
        $result = new FieldSecurityFilter(
            $this->makeConditionResource("published"),
            $user,
            $resolver
        )->filterRead(new Dictionary(["body" => "visible"]));
        $this->assertSame("visible", $result["body"]);
    }

    #[Test]
    public function filterReadRemovesFieldWhenResourceConditionFails(): void
    {
        $user = $this->makeUser();
        $resolver = new AccessConditionResolver();
        $result = new FieldSecurityFilter(
            $this->makeConditionResource("draft"),
            $user,
            $resolver
        )->filterRead(new Dictionary(["body" => "hidden"]));
        $this->assertNull($result["body"]);
    }
}
