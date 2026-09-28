<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Exception;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
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
use Sabatier\Service\AuthorizationResolver;
use Sabatier\Service\AuthorizationService;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\FieldLevelSecurityPolicy;
use Sabatier\Service\FieldSecurityPolicy;
use Sabatier\Service\ForbiddenException;
use Sabatier\Service\InMemoryAuthorizationCache;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Sabatier\Service\Owner;
use Sabatier\Service\Readable;
use Throwable;

class OwnedToolEntityFixture extends ManagedObject
{
    #[Owner]
    public ?Authorizable $createdBy = null;
}

class UnownedToolEntityFixture extends ManagedObject
{
    public string $title = "";
}

#[Readable(["Finance"])]
class RoleGuardedReadToolEntityFixture extends ManagedObject
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
    #[Override]
    protected AuthorizationService $authorizationService {
        /**
         * @throws ReflectionException
         */
        get => new AuthorizationService(new ReflectionClass(AuthorizationResolver::class)->newInstanceWithoutConstructor(), new InMemoryAuthorizationCache());
    }

    public function __construct(ManagedObjectContext $context, ModelDescriptor $descriptor, FieldSecurityPolicy $fieldSecurityPolicy)
    {
        parent::__construct($context, $descriptor);
        $this->fieldSecurityPolicy = $fieldSecurityPolicy;
    }

    #[Override]
    protected function executeCore(Dictionary $arguments): ArrayClass
    {
        return $this->textResult("ok");
    }

    /** @throws Exception */
    public function exposedEnforceEntityAuthorization(string $resource, AuthorizationType $action): void
    {
        $this->enforceEntityAuthorization($resource, $action);
    }
}

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
        $roles = new Set(new ArrayClass($roleNames)->map($this->makeRole(...))->array);
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

    /** @throws ReflectionException */
    private function makeTool(?Authorizable $user, ArrayClass $scopes, bool $isSecurityEnabled = true): SecurityProbeTool
    {
        return $this->makeToolWithPolicy($this->makePolicy($user, $scopes, $isSecurityEnabled));
    }

    private function makePolicy(?Authorizable $user, ArrayClass $scopes, bool $isSecurityEnabled = true): FieldSecurityPolicy
    {
        return new FieldLevelSecurityPolicy(new AuthorizationContext($user, $scopes, $isSecurityEnabled));
    }

    /** @throws ReflectionException */
    private function makeToolWithPolicy(FieldSecurityPolicy $policy): SecurityProbeTool
    {
        $context = new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
        $descriptor = new ReflectionClass(ModelDescriptor::class)->newInstanceWithoutConstructor();
        return new SecurityProbeTool($context, $descriptor, $policy);
    }

    /**
     * @param class-string<ManagedObject>|null $managedObjectClassName
     * @throws ReflectionException
     */
    private function makeEntity(string $name, ?string $managedObjectClassName): EntityDescription
    {
        $entity = new ReflectionClass(EntityDescription::class)->newInstanceWithoutConstructor();
        $entity->name = $name;
        $entity->managedObjectClassName = $managedObjectClassName;
        return $entity;
    }

    /** @throws ReflectionException */
    private function makeOwnedResource(?Authorizable $owner): OwnedToolEntityFixture
    {
        /** @var OwnedToolEntityFixture $resource */
        $resource = new ReflectionClass(OwnedToolEntityFixture::class)->newInstanceWithoutConstructor();
        $entity = $this->makeEntity("Ownable", OwnedToolEntityFixture::class);
        $context = new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
        new ReflectionClass(ManagedObject::class)->getProperty("entity")->setValue($resource, $entity);
        new ReflectionClass(ManagedObject::class)->getProperty("managedObjectContext")->setValue($resource, $context);
        $resource->createdBy = $owner;
        return $resource;
    }

    /** @throws Exception */
    #[Test]
    public function ownReadPredicateBuiltWhenOwnScopeAndOwnerFieldPresent(): void
    {
        $policy = $this->makePolicy($this->makeUser(), new ArrayClass(["Ownable:read:own"]));
        $predicate = $policy->ownReadPredicate($this->makeEntity("Ownable", OwnedToolEntityFixture::class));
        $this->assertInstanceOf(ComparisonPredicate::class, $predicate);
    }

    /** @throws Exception */
    #[Test]
    public function ownReadPredicateNullWhenSecurityDisabled(): void
    {
        $policy = $this->makePolicy($this->makeUser(), new ArrayClass(["Ownable:read:own"]), false);
        $this->assertNull($policy->ownReadPredicate($this->makeEntity("Ownable", OwnedToolEntityFixture::class)));
    }

    /** @throws Exception */
    #[Test]
    public function ownReadPredicateNullWithoutOwnScope(): void
    {
        $policy = $this->makePolicy($this->makeUser(), new ArrayClass(["Ownable:read"]));
        $this->assertNull($policy->ownReadPredicate($this->makeEntity("Ownable", OwnedToolEntityFixture::class)));
    }

    /** @throws Exception */
    #[Test]
    public function ownReadPredicateNullWithoutOwnerField(): void
    {
        $policy = $this->makePolicy($this->makeUser(), new ArrayClass(["Plain:read:own"]));
        $this->assertNull($policy->ownReadPredicate($this->makeEntity("Plain", UnownedToolEntityFixture::class)));
    }

    /** @throws Exception */
    #[Test]
    public function applyReadScopeSetsPredicateWhenRequestHasNone(): void
    {
        $policy = $this->makePolicy($this->makeUser(), new ArrayClass(["Ownable:read:own"]));
        $request = new FetchRequest();
        $request->entity = $this->makeEntity("Ownable", OwnedToolEntityFixture::class);
        $policy->applyReadScope($request);
        $this->assertInstanceOf(ComparisonPredicate::class, $request->predicate);
    }

    /** @throws Exception */
    #[Test]
    public function applyReadScopeAndCombinesWithExistingPredicate(): void
    {
        $policy = $this->makePolicy($this->makeUser(), new ArrayClass(["Ownable:read:own"]));
        $request = new FetchRequest();
        $request->entity = $this->makeEntity("Ownable", OwnedToolEntityFixture::class);
        $request->predicate = Predicate::format("%K = %@", new ArrayClass(["title", "x"]));
        $policy->applyReadScope($request);
        $this->assertInstanceOf(CompoundPredicate::class, $request->predicate);
    }

    /** @throws Exception */
    #[Test]
    public function applyReadScopeLeavesRequestUntouchedWhenSecurityDisabled(): void
    {
        $policy = $this->makePolicy($this->makeUser(), new ArrayClass(["Ownable:read:own"]), false);
        $request = new FetchRequest();
        $request->entity = $this->makeEntity("Ownable", OwnedToolEntityFixture::class);
        $policy->applyReadScope($request);
        $this->assertNull($request->predicate);
    }

    /**
     * @param class-string<ManagedObject> $className
     * @throws ReflectionException
     */
    private function makeObjectIDRequest(string $entityName, string $className): FetchRequest
    {
        $request = new FetchRequest();
        $request->entity = $this->makeEntity($entityName, $className);
        $request->predicate = Predicate::format("%K = %d", new ArrayClass(["objectID", 1]));
        return $request;
    }

    /** @throws Exception */
    #[Test]
    public function applyReadScopeNarrowsObjectIDLookupWhenResourceReadExcludesTheSubject(): void
    {
        $policy = $this->makePolicy($this->makeUser("Sales"), new ArrayClass());
        $request = $this->makeObjectIDRequest("Guarded", RoleGuardedReadToolEntityFixture::class);
        $policy->applyReadScope($request);
        $this->assertInstanceOf(CompoundPredicate::class, $request->predicate);
        $this->assertStringContainsStringIgnoringCase("FALSEPREDICATE", $request->predicate->predicateFormat);
    }

    /** @throws Exception */
    #[Test]
    public function applyReadScopeLeavesObjectIDLookupIntactForAllowedRole(): void
    {
        $policy = $this->makePolicy($this->makeUser("Finance"), new ArrayClass());
        $request = $this->makeObjectIDRequest("Guarded", RoleGuardedReadToolEntityFixture::class);
        $policy->applyReadScope($request);
        $this->assertInstanceOf(ComparisonPredicate::class, $request->predicate);
    }

    /** @throws Exception */
    #[Test]
    public function applyReadScopeLeavesObjectIDLookupIntactForUnguardedClass(): void
    {
        $policy = $this->makePolicy($this->makeUser("Sales"), new ArrayClass());
        $request = $this->makeObjectIDRequest("Plain", UnownedToolEntityFixture::class);
        $policy->applyReadScope($request);
        $this->assertInstanceOf(ComparisonPredicate::class, $request->predicate);
    }

    /** @throws Exception */
    #[Test]
    public function enforceOwnershipThrowsForNonOwnerWithOwnScope(): void
    {
        $policy = $this->makePolicy($this->makeUser("User"), new ArrayClass(["Ownable:update:own"]));
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessageIsOrContains("belongs to another user");
        $policy->enforceOwnership($this->makeOwnedResource($this->makeUser("Other")), AuthorizationType::update);
    }

    /** @throws Exception */
    #[Test]
    public function enforceOwnershipPassesForOwner(): void
    {
        $user = $this->makeUser("User");
        $policy = $this->makePolicy($user, new ArrayClass(["Ownable:update:own"]));
        $policy->enforceOwnership($this->makeOwnedResource($user), AuthorizationType::update);
        $this->assertTrue(true);
    }

    /** @throws Exception */
    #[Test]
    public function enforceOwnershipChecksTheActionItIsGiven(): void
    {
        $policy = $this->makePolicy($this->makeUser("User"), new ArrayClass(["Ownable:read:own", "Ownable:delete:all"]));
        $policy->enforceOwnership($this->makeOwnedResource($this->makeUser("Other")), AuthorizationType::delete);
        $this->assertTrue(true);
    }

    /** @throws Exception */
    #[Test]
    public function enforceOwnershipRestrictsTheActionItIsGiven(): void
    {
        $policy = $this->makePolicy($this->makeUser("User"), new ArrayClass(["Ownable:update:all", "Ownable:delete:own"]));
        $this->expectException(ForbiddenException::class);
        $policy->enforceOwnership($this->makeOwnedResource($this->makeUser("Other")), AuthorizationType::delete);
    }

    /** @throws Exception */
    #[Test]
    public function enforceOwnershipPassesWhenSecurityDisabled(): void
    {
        $policy = $this->makePolicy($this->makeUser(), new ArrayClass(["Ownable:update:own"]), false);
        $policy->enforceOwnership($this->makeOwnedResource($this->makeUser("Other")), AuthorizationType::update);
        $this->assertTrue(true);
    }

    /** @throws Exception */
    #[Test]
    public function enforceEntityAuthorizationIsNoOpWhenSecurityDisabled(): void
    {
        $tool = $this->makeTool($this->makeUser(), new ArrayClass(), false);
        $tool->exposedEnforceEntityAuthorization("Ownable", AuthorizationType::read);
        $this->assertTrue(true);
    }

    /** @throws Exception */
    #[Test]
    public function enforceEntityAuthorizationThrowsWithoutUser(): void
    {
        $tool = $this->makeTool(null, new ArrayClass(["Ownable:read"]));
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessageIs("You must be authenticated to perform this action.");
        $tool->exposedEnforceEntityAuthorization("Ownable", AuthorizationType::read);
    }

    /** @throws Throwable */
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
            protected function executeCore(Dictionary $arguments): ArrayClass
            {
                throw new ForbiddenException("You don't have permission to delete \"Order\".");
            }
        };
        $result = new ToolRegistry(new ArrayClass([$tool]))->call("denying_tool", new Dictionary());
        $this->assertTrue($result->isError);
        $this->assertSame("You don't have permission to delete \"Order\". Do not retry this call.", $result->text);
    }

    /** @throws Throwable */
    #[Test]
    public function theRegistryAuthorizesTheEntityArgumentBeforeRunning(): void
    {
        $tool = $this->makeTool($this->makeUser(), new ArrayClass(["Ownable:update"]));
        $this->assertFalse(new ToolRegistry(new ArrayClass([$tool]))->call("security_probe", new Dictionary(["entity" => "Ownable"]))->isError);
    }

    /** @throws Throwable */
    #[Test]
    public function theRegistryDeniesAnEntityTheUserMayNotWriteWithoutRunningTheTool(): void
    {
        $user = $this->makeUser();
        new InMemoryAuthorizationCache()->setAuthorizableAuthorizations($user, new ArrayClass());
        $tool = $this->makeTool($user, new ArrayClass(["Ownable:create"]));
        $result = new ToolRegistry(new ArrayClass([$tool]))->call("security_probe", new Dictionary(["entity" => "Ownable"]));
        $this->assertTrue($result->isError);
        $this->assertSame("You don't have permission to update \"Ownable\". Do not retry this call.", $result->text);
    }

    /** @throws Throwable */
    #[Test]
    public function theRegistryRunsAToolThatNamesNoResourceUnchecked(): void
    {
        $tool = $this->makeTool(null, new ArrayClass());
        $this->assertFalse(new ToolRegistry(new ArrayClass([$tool]))->call("security_probe", new Dictionary())->isError);
    }

    #[Override]
    protected function tearDown(): void
    {
        new InMemoryAuthorizationCache()->invalidateAll();
        parent::tearDown();
    }
}
