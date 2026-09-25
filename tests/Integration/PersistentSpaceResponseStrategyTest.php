<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Sabatier\CoreData\AttributeDescription;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Set;
use Sabatier\Service\Authorizable;
use Sabatier\Service\AuthorizableRole;
use Sabatier\Service\AuthorizationContext;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\FieldLevelSecurityPolicy;
use Sabatier\Service\ForbiddenException;
use Sabatier\Service\Owner;
use Sabatier\Service\PersistentSpaceResponseStrategy;
use Sabatier\Service\ReadPersistentSpaceResponseStrategy;
use Sabatier\Service\Request;

class OwnedOrderStrategyFixture extends ManagedObject
{
    #[Owner]
    public ?Authorizable $createdBy = null;
}

/**
 * Fixes the lookup every CRUD strategy inherits, and the policy values it exposes to its subclasses.
 * Executing the fetch is not exercised: that needs a store behind the context.
 */
final class PersistentSpaceResponseStrategyTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REQUEST_URI"] = "/Order";
        $_SERVER["REQUEST_METHOD"] = "GET";
        unset($_SERVER["HTTP_SERIALIZATION"]);
        ObjectClass::$staticAssociatedValues = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        ObjectClass::$staticAssociatedValues = [];
        parent::tearDown();
    }

    /** @throws ReflectionException */
    #[Test]
    public function theLookupIsScopedToTheStrategysOwnEntity(): void
    {
        $this->assertSame("Order", $this->fetchRequestFor(42)->entity?->name);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theLookupMatchesTheObjectIdentifier(): void
    {
        $this->assertStringContainsString("42", $this->fetchRequestFor(42)->predicate?->predicateFormat ?? "");
    }

    /** @throws ReflectionException */
    #[Test]
    public function aNumericIdentifierArrivingAsTextIsMatchedAsANumber(): void
    {
        // A query-string objectID arrives as a string; matching it verbatim would never find the row.
        // Expression::expressionForConstantValue normalizes numeric strings on its own, so the
        // strategy's own coercion is belt-and-braces rather than what makes this hold.
        $this->assertSame($this->fetchRequestFor(42)->predicate?->predicateFormat, $this->fetchRequestFor("42")->predicate?->predicateFormat);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aNonNumericIdentifierIsMatchedAsItCame(): void
    {
        $this->assertStringContainsString("abc", $this->fetchRequestFor("abc")->predicate?->predicateFormat ?? "");
    }

    /** @throws ReflectionException */
    #[Test]
    public function withoutASerializationHeaderTheLookupFallsBackToTheEntitysOwnAttributes(): void
    {
        $this->assertSame(["total"], $this->fetchRequestFor(42)->serialization->keys->array);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theSerializationHeaderIsCarriedOntoTheLookup(): void
    {
        $_SERVER["HTTP_SERIALIZATION"] = "{\"name\": true}";
        $this->assertSame(["name"], $this->fetchRequestFor(42)->serialization->keys->array);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theSubjectIsTakenFromTheSecurityPolicy(): void
    {
        $user = $this->user("auditor");
        $strategy = $this->strategy($this->policy(true, $user));
        $this->assertSame($user, new ReflectionProperty(PersistentSpaceResponseStrategy::class, "user")->getValue($strategy));
    }

    /** @throws ReflectionException */
    #[Test]
    public function whetherSecurityIsEnabledIsTakenFromTheSecurityPolicy(): void
    {
        $this->assertTrue($this->isSecurityEnabled($this->strategy($this->policy(true, null))));
        $this->assertFalse($this->isSecurityEnabled($this->strategy($this->policy(false, null))));
    }

    /** @throws ReflectionException */
    #[Test]
    public function theOwnScopeIsAnsweredPerEntity(): void
    {
        $strategy = $this->strategy($this->policy(true, $this->user(), new ArrayClass(["Order:read:own"])));
        $hasOwnScopeFor = new ReflectionMethod(PersistentSpaceResponseStrategy::class, "hasOwnScopeFor");
        $this->assertTrue($hasOwnScopeFor->invoke($strategy, "Order", AuthorizationType::read));
        $this->assertFalse($hasOwnScopeFor->invoke($strategy, "Invoice", AuthorizationType::read));
    }

    #[Test]
    public function theOwnScopeIsAnsweredPerAction(): void
    {
        $policy = $this->policy(true, $this->user(), new ArrayClass(["Order:read:own", "Order:delete:all"]));
        $this->assertTrue($policy->hasOwnScopeFor("Order", AuthorizationType::read));
        $this->assertFalse($policy->hasOwnScopeFor("Order", AuthorizationType::delete));
        $this->assertFalse($policy->hasOwnScopeFor("Order", AuthorizationType::update));
    }

    #[Test]
    public function anOwnScopeOnAnyActionRestrictsEveryAction(): void
    {
        $policy = $this->policy(true, $this->user(), new ArrayClass(["Order:any:own"]));
        $this->assertTrue($policy->hasOwnScopeFor("Order", AuthorizationType::read));
        $this->assertTrue($policy->hasOwnScopeFor("Order", AuthorizationType::delete));
    }

    #[Test]
    public function aScopeOnAllRowsWinsOverAnOwnScopeForTheSameAction(): void
    {
        $this->assertFalse($this->policy(true, $this->user(), new ArrayClass(["Order:read:own", "Order:read:all"]))->hasOwnScopeFor("Order", AuthorizationType::read));
        $this->assertFalse($this->policy(true, $this->user(), new ArrayClass(["Order:read:own", "Order:any:all"]))->hasOwnScopeFor("Order", AuthorizationType::read));
    }

    /** @throws ReflectionException */
    #[Test]
    public function aDeleteIsNotRestrictedByAnOwnScopeOnReads(): void
    {
        $this->enforceOwnership(HTTPRequestMethod::delete, new ArrayClass(["Order:read:own", "Order:delete:all"]));
        $this->expectNotToPerformAssertions();
    }

    /** @throws ReflectionException */
    #[Test]
    public function aDeleteIsRestrictedByAnOwnScopeOnDeletes(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->enforceOwnership(HTTPRequestMethod::delete, new ArrayClass(["Order:update:all", "Order:delete:own"]));
    }

    /** @throws ReflectionException */
    #[Test]
    public function anUpdateIsRestrictedByAnOwnScopeOnUpdates(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->enforceOwnership(HTTPRequestMethod::patch, new ArrayClass(["Order:update:own", "Order:delete:all"]));
    }

    /**
     * @param ArrayClass<string> $scopes
     * @throws ReflectionException
     */
    private function enforceOwnership(string $method, ArrayClass $scopes): void
    {
        new ReflectionMethod(PersistentSpaceResponseStrategy::class, "enforceOwnership")->invoke($this->strategy($this->policy(true, $this->user(), $scopes), $method), $this->ownedOrder($this->user()));
    }

    /** @throws ReflectionException */
    private function ownedOrder(Authorizable $owner): OwnedOrderStrategyFixture
    {
        $order = new ReflectionClass(OwnedOrderStrategyFixture::class)->newInstanceWithoutConstructor();
        $entity = new EntityDescription();
        $entity->name = "Order";
        new ReflectionProperty(ManagedObject::class, "entity")->setValue($order, $entity);
        new ReflectionProperty(ManagedObject::class, "managedObjectContext")->setValue($order, new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor());
        $order->createdBy = $owner;
        return $order;
    }

    private function isSecurityEnabled(PersistentSpaceResponseStrategy $strategy): bool
    {
        /** @var bool */
        return new ReflectionProperty(PersistentSpaceResponseStrategy::class, "isSecurityEnabled")->getValue($strategy);
    }

    /** @throws ReflectionException */
    private function fetchRequestFor(int|string $objectID): FetchRequest
    {
        /** @var FetchRequest */
        return new ReflectionMethod(PersistentSpaceResponseStrategy::class, "fetchRequestFor")->invoke($this->strategy($this->policy(false, null)), $objectID);
    }

    /** @param ArrayClass<string>|null $scopes */
    private function policy(bool $isSecurityEnabled, ?Authorizable $user, ?ArrayClass $scopes = null): FieldLevelSecurityPolicy
    {
        return new FieldLevelSecurityPolicy(new AuthorizationContext($user, $scopes ?? new ArrayClass(), $isSecurityEnabled));
    }

    /** @throws ReflectionException */
    private function strategy(FieldLevelSecurityPolicy $policy, string $method = HTTPRequestMethod::get): PersistentSpaceResponseStrategy
    {
        $request = new Request();
        $request->httpMethod = $method;
        $total = new AttributeDescription();
        $total->name = "total";
        $total->type = AttributeType::decimal;
        $entity = new EntityDescription();
        $entity->name = "Order";
        $entity->properties = new ArrayClass([$total]);
        // An entity stays editable — and refuses to derive attributesByName — until a model owns it.
        /** @noinspection PhpObjectFieldsAreOnlyWrittenInspection */
        $model = new ManagedObjectModel();
        $model->entities = new ArrayClass([$entity]);
        return new ReadPersistentSpaceResponseStrategy($request, $entity, new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor(), $policy);
    }

    /** @noinspection PhpSameParameterValueInspection */
    private function user(string ...$roleNames): Authorizable
    {
        $roles = new Set(new ArrayClass($roleNames)->map($this->role(...))->array);
        return new class ($roles) implements Authorizable {
            public function __construct(private readonly Set $userRoles)
            {
            }

            public string $username { get => "ada"; }
            public ?string $password { get => null; }
            public bool $isEnabled { get => true; }
            public int $refreshTokenVersion { get => 1; set {} }
            public Set $roles { get => $this->userRoles; }

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

    private function role(string $name): AuthorizableRole
    {
        return new class ($name) implements AuthorizableRole {
            public function __construct(private readonly string $roleName)
            {
            }

            public string $name { get => $this->roleName; }
            public Set $authorizations { get => new Set(); }
        };
    }
}
