<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\CompoundPredicate;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\Set;
use Sabatier\Service\Authorizable;
use Sabatier\Service\AuthorizableRole;
use Sabatier\Service\AuthorizationContext;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\FieldLevelSecurityPolicy;
use Sabatier\Service\FieldSecurityPolicy;
use Sabatier\Service\ForbiddenException;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Sabatier\Service\Owner;

// --- Fixtures ---

class OwnedToolEntityFixture extends ManagedObject
{
    #[Owner]
    public ?Authorizable $createdBy = null;
}

class UnownedToolEntityFixture extends ManagedObject
{
    public string $title = "";
}

final class SecurityProbeTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "security_probe";
    }
    #[Override]
    public array $inputSchema {
        get => ["type" => "object"];
    }
    #[Override]
    protected bool $isSecurityEnabled {
        get => $this->fieldSecurityPolicy->isSecurityEnabled;
    }

    public function __construct(ManagedObjectContext $context, ModelDescriptor $descriptor, FieldSecurityPolicy $fieldSecurityPolicy)
    {
        parent::__construct($context, $descriptor);
        $this->fieldSecurityPolicy = $fieldSecurityPolicy;
    }

    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        return $this->textResult("ok");
    }

    public function exposedOwnershipPredicate(EntityDescription $entity): ?Predicate
    {
        return $this->ownershipPredicate($entity);
    }

    public function exposedApplyOwnershipScope(FetchRequest $request): void
    {
        $this->applySecurityScope($request);
    }

    public function exposedEnforceOwnership(ManagedObject $object): void
    {
        $this->enforceOwnership($object);
    }

    public function exposedEnforceEntityAuthorization(string $resource, AuthorizationType $action): void
    {
        $this->enforceEntityAuthorization($resource, $action);
    }
}

// --- Tests ---

/**
 * Exercises the security helpers `AbstractTool` inherits from the field security policy —
 * ownership predicate injection, ownership enforcement and the per-entity authorization
 * gate. The token-scope grant path of `enforceEntityAuthorization` is covered by
 * `AuthorizationServiceTest`; it is not repeated here because it requires the shared
 * application's persistent container.
 */
final class MCPToolSecurityTest extends TestCase
{
    private function makeRole(string $name): AuthorizableRole
    {
        return new class($name) implements AuthorizableRole {
            public function __construct(private readonly string $_name)
            {
            }
            public string $name {
                get => $this->_name;
            }
            public Set $authorizations {
                get => new Set();
            }
        };
    }

    private function makeUser(string ...$roleNames): Authorizable
    {
        $roles = new Set(array_map(fn(string $n): AuthorizableRole => $this->makeRole($n), $roleNames));
        return new class($roles) implements Authorizable {
            public function __construct(private readonly Set $userRoles)
            {
            }
            public string $username {
                get => "user";
            }
            public ?string $password {
                get => null;
            }
            public bool $isEnabled {
                get => true;
            }
            public int $refreshTokenVersion {
                get => 1;
                set {
                }
            }
            public Set $roles {
                get => $this->userRoles;
            }
            public function isEqual(mixed $other): bool
            {
                return $this === $other;
            }
            public static function defaultRepresentation(): Dictionary
            {
                return new Dictionary();
            }
        };
    }

    private function makeTool(?Authorizable $user, ArrayClass $scopes, bool $isSecurityEnabled = true): SecurityProbeTool
    {
        $policy = new FieldLevelSecurityPolicy(new AuthorizationContext($user, $scopes, $isSecurityEnabled));
        return $this->makeToolWithPolicy($policy);
    }

    private function makeToolWithPolicy(FieldSecurityPolicy $policy): SecurityProbeTool
    {
        $context = new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
        $descriptor = new ReflectionClass(ModelDescriptor::class)->newInstanceWithoutConstructor();
        return new SecurityProbeTool($context, $descriptor, $policy);
    }

    /**
     * @param class-string<ManagedObject>|null $managedObjectClassName
     */
    private function makeEntity(string $name, ?string $managedObjectClassName): EntityDescription
    {
        $entity = new ReflectionClass(EntityDescription::class)->newInstanceWithoutConstructor();
        $entity->name = $name;
        $entity->managedObjectClassName = $managedObjectClassName;
        return $entity;
    }

    /**
     * Creates an OwnedToolEntityFixture with the minimal CoreData stubs needed so that
     * ManagedObject::valueForKey() can fall through to ObjectClass::valueForKey(),
     * which reads the PHP property directly.
     */
    private function makeOwnedResource(?Authorizable $owner, string $entityName): OwnedToolEntityFixture
    {
        /** @var OwnedToolEntityFixture $resource */
        $resource = new ReflectionClass(OwnedToolEntityFixture::class)->newInstanceWithoutConstructor();
        $entity = $this->makeEntity($entityName, OwnedToolEntityFixture::class);
        $context = new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
        new ReflectionClass(ManagedObject::class)->getProperty("entity")->setValue($resource, $entity);
        new ReflectionClass(ManagedObject::class)->getProperty("managedObjectContext")->setValue($resource, $context);
        $resource->createdBy = $owner;
        return $resource;
    }

    // --- ownershipPredicate / applySecurityScope ---

    #[Test]
    public function ownershipPredicateBuiltWhenOwnScopeAndOwnerFieldPresent(): void
    {
        $tool = $this->makeTool($this->makeUser(), new ArrayClass(["Ownable:read:own"]));
        $predicate = $tool->exposedOwnershipPredicate($this->makeEntity("Ownable", OwnedToolEntityFixture::class));
        $this->assertInstanceOf(ComparisonPredicate::class, $predicate);
    }

    #[Test]
    public function ownershipPredicateNullWhenSecurityDisabled(): void
    {
        $tool = $this->makeTool($this->makeUser(), new ArrayClass(["Ownable:read:own"]), false);
        $this->assertNull($tool->exposedOwnershipPredicate($this->makeEntity("Ownable", OwnedToolEntityFixture::class)));
    }

    #[Test]
    public function ownershipPredicateNullWithoutOwnScope(): void
    {
        $tool = $this->makeTool($this->makeUser(), new ArrayClass(["Ownable:read"]));
        $this->assertNull($tool->exposedOwnershipPredicate($this->makeEntity("Ownable", OwnedToolEntityFixture::class)));
    }

    #[Test]
    public function ownershipPredicateNullWithoutOwnerField(): void
    {
        $tool = $this->makeTool($this->makeUser(), new ArrayClass(["Plain:read:own"]));
        $this->assertNull($tool->exposedOwnershipPredicate($this->makeEntity("Plain", UnownedToolEntityFixture::class)));
    }

    #[Test]
    public function applySecurityScopeSetsPredicateWhenRequestHasNone(): void
    {
        $tool = $this->makeTool($this->makeUser(), new ArrayClass(["Ownable:read:own"]));
        $request = new FetchRequest();
        $request->entity = $this->makeEntity("Ownable", OwnedToolEntityFixture::class);
        $tool->exposedApplyOwnershipScope($request);
        $this->assertInstanceOf(ComparisonPredicate::class, $request->predicate);
    }

    #[Test]
    public function applySecurityScopeAndCombinesWithExistingPredicate(): void
    {
        $tool = $this->makeTool($this->makeUser(), new ArrayClass(["Ownable:read:own"]));
        $request = new FetchRequest();
        $request->entity = $this->makeEntity("Ownable", OwnedToolEntityFixture::class);
        $request->predicate = Predicate::format("%K = %@", new ArrayClass(["title", "x"]));
        $tool->exposedApplyOwnershipScope($request);
        $this->assertInstanceOf(CompoundPredicate::class, $request->predicate);
    }

    #[Test]
    public function applySecurityScopeLeavesRequestUntouchedWhenSecurityDisabled(): void
    {
        $tool = $this->makeTool($this->makeUser(), new ArrayClass(["Ownable:read:own"]), false);
        $request = new FetchRequest();
        $request->entity = $this->makeEntity("Ownable", OwnedToolEntityFixture::class);
        $tool->exposedApplyOwnershipScope($request);
        $this->assertNull($request->predicate);
    }

    // --- enforceOwnership ---

    #[Test]
    public function enforceOwnershipThrowsForNonOwnerWithOwnScope(): void
    {
        $user = $this->makeUser("User");
        $tool = $this->makeTool($user, new ArrayClass(["Ownable:update:own"]));
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage("belongs to another user");
        $tool->exposedEnforceOwnership($this->makeOwnedResource($this->makeUser("Other"), "Ownable"));
    }

    #[Test]
    public function enforceOwnershipPassesForOwner(): void
    {
        $user = $this->makeUser("User");
        $tool = $this->makeTool($user, new ArrayClass(["Ownable:update:own"]));
        $tool->exposedEnforceOwnership($this->makeOwnedResource($user, "Ownable"));
        $this->assertTrue(true);
    }

    #[Test]
    public function enforceOwnershipPassesWhenSecurityDisabled(): void
    {
        $tool = $this->makeTool($this->makeUser(), new ArrayClass(["Ownable:update:own"]), false);
        $tool->exposedEnforceOwnership($this->makeOwnedResource($this->makeUser("Other"), "Ownable"));
        $this->assertTrue(true);
    }

    // --- enforceEntityAuthorization ---

    #[Test]
    public function enforceEntityAuthorizationIsNoOpWhenSecurityDisabled(): void
    {
        $tool = $this->makeTool($this->makeUser(), new ArrayClass(), false);
        $tool->exposedEnforceEntityAuthorization("Ownable", AuthorizationType::read);
        $this->assertTrue(true);
    }

    #[Test]
    public function enforceEntityAuthorizationThrowsWithoutUser(): void
    {
        $tool = $this->makeTool(null, new ArrayClass(["Ownable:read"]));
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage("must be authenticated");
        $tool->exposedEnforceEntityAuthorization("Ownable", AuthorizationType::read);
    }

    // --- ToolRegistry denial funnel ---

    #[Test]
    public function toolRegistryAppendsRetryStopperToAuthorizationDenials(): void
    {
        $context = new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
        $descriptor = new ReflectionClass(ModelDescriptor::class)->newInstanceWithoutConstructor();
        $tool = new class($context, $descriptor) extends AbstractTool {
            #[Override]
            public string $name {
                get => "denying_tool";
            }
            #[Override]
            public array $inputSchema {
                get => ["type" => "object"];
            }

            #[Override]
            public function execute(Dictionary $arguments): ArrayClass
            {
                throw new ForbiddenException("You don't have permission to delete \"Order\".");
            }
        };
        $result = new ToolRegistry(new ArrayClass([$tool]))->call("denying_tool", new Dictionary());
        $this->assertTrue($result->isError);
        $this->assertSame("You don't have permission to delete \"Order\". Do not retry this call.", $result->text);
    }
}
