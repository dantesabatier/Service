<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\RelationshipDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Nil;
use Sabatier\Foundation\Notification;
use Sabatier\Foundation\Set;
use Sabatier\Service\Authorizable;
use Sabatier\Service\AuthorizableRole;
use Sabatier\Service\AuthorizationResolver;
use Sabatier\Service\AuthorizationScope;
use Sabatier\Service\AuthorizationService;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\CachedAuthorization;
use Sabatier\Service\ForbiddenException;
use Sabatier\Service\InMemoryAuthorizationCache;
use Sabatier\Service\Owner;
use Sabatier\Service\Readable;
use Sabatier\Service\RequestSecurityContext;
use Sabatier\Service\Testing\FixesRequestSecurityContext;
use Sabatier\Service\Writable;
use Sabatier\Service\WriteAuthorizationObserver;
use const Sabatier\CoreData\ManagedObjectContextWillSave;

class ObservedOrderFixture extends ManagedObject
{
    public string $status = "";
    public ?ManagedObject $customer = null;
}

class ObservedCustomerFixture extends ManagedObject
{
    public ?Set $orders = null;
}

#[Readable(["Finance"])]
class ObservedGuardedCustomerFixture extends ManagedObject
{
}

class ObservedOwnedFixture extends ManagedObject
{
    #[Owner]
    public ?Authorizable $createdBy = null;
}

#[Writable(where: "status != %@", arguments: ["closed"])]
class ObservedClosableFixture extends ManagedObject
{
    public string $status = "";
}

class ObservedPricedFixture extends ManagedObject
{
    public string $status = "";
    #[Writable(["Finance"])]
    public float $price = 0.0;
    #[Writable(where: "status != %@", arguments: ["locked"])]
    public string $note = "";
}

final class WriteAuthorizationObserverTest extends TestCase
{
    use FixesRequestSecurityContext;

    private ManagedObjectContext $managedObjectContext;
    private EntityDescription $orderEntity;
    private EntityDescription $customerEntity;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->managedObjectContext = new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
        $this->setChanges();
        $toCustomer = $this->relationship("customer", false, "orders");
        $toOrders = $this->relationship("orders", true, "customer");
        new ReflectionProperty(RelationshipDescription::class, "inverseRelationship")->setRawValue($toCustomer, $toOrders);
        new ReflectionProperty(RelationshipDescription::class, "inverseRelationship")->setRawValue($toOrders, $toCustomer);
        $this->orderEntity = $this->entity("Order", ObservedOrderFixture::class, new Dictionary(["customer" => $toCustomer]));
        $this->customerEntity = $this->entity("Customer", ObservedCustomerFixture::class, new Dictionary(["orders" => $toOrders]));
    }

    #[Override]
    protected function tearDown(): void
    {
        new InMemoryAuthorizationCache()->invalidateAll();
        parent::tearDown();
    }

    #[Test]
    public function aSaveOutsideAnyContextIsLeftAlone(): void
    {
        $this->setChanges(inserted: new Set([$this->order()]));
        $this->willSave(new ArrayClass());
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function aSaveUnderADisabledContextIsLeftAlone(): void
    {
        $this->fixRequestSecurityContext(new RequestSecurityContext($this->user("disabled"), new ArrayClass(), false, $this->managedObjectContext));
        $this->setChanges(inserted: new Set([$this->order()]));
        $this->willSave(new ArrayClass());
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function aSaveOfAPublicResponderIsLeftAlone(): void
    {
        $this->fixRequestSecurityContext(new RequestSecurityContext($this->user("public"), new ArrayClass(), true, $this->managedObjectContext, true));
        $this->setChanges(inserted: new Set([$this->order()]));
        $this->willSave(new ArrayClass());
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function aSaveWithoutASubjectIsLeftAlone(): void
    {
        $this->fixRequestSecurityContext($this->restrictedRequestSecurityContext(null, managedObjectContext: $this->managedObjectContext));
        $this->setChanges(inserted: new Set([$this->order()]));
        $this->willSave(new ArrayClass());
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function aSaveOfAnotherContextIsLeftAlone(): void
    {
        $this->fixRequestSecurityContext($this->restrictedRequestSecurityContext($this->user("other"), managedObjectContext: new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor()));
        $this->setChanges(inserted: new Set([$this->order()]));
        $this->willSave(new ArrayClass());
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function aSystemWriteIsLeftAlone(): void
    {
        $this->fixRequestSecurityContext($this->restrictedRequestSecurityContext($this->user("system"), managedObjectContext: $this->managedObjectContext));
        $this->setChanges(inserted: new Set([$this->order()]));
        RequestSecurityContext::performAsSystem(fn() => $this->willSave(new ArrayClass()));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function anInsertNeedsCreateOnItsEntity(): void
    {
        $this->fixUser("inserter");
        $this->setChanges(inserted: new Set([$this->order()]));
        $this->expectExceptionObject(new ForbiddenException("You don't have permission to create \"Order\" rows."));
        $this->willSave(new ArrayClass([$this->grant("Order", AuthorizationType::update)]));
    }

    #[Test]
    public function anInsertWithCreateOnItsEntityPasses(): void
    {
        $this->fixUser("creator");
        $this->setChanges(inserted: new Set([$this->order()]));
        $this->willSave(new ArrayClass([$this->grant("Order", AuthorizationType::create)]));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function anUpdateNeedsUpdateOnItsEntity(): void
    {
        $this->fixUser("updater");
        $this->setChanges(updated: new Set([$this->order(changed: new Dictionary(["status" => "paid"]))]));
        $this->expectExceptionObject(new ForbiddenException("You don't have permission to update \"Order\" rows."));
        $this->willSave(new ArrayClass([$this->grant("Order", AuthorizationType::create)]));
    }

    #[Test]
    public function aDeleteNeedsDeleteOnItsEntity(): void
    {
        $this->fixUser("deleter");
        $order = $this->order();
        $this->setChanges(deleted: new Set([$order]));
        $this->expectExceptionObject(new ForbiddenException("You don't have permission to delete \"Order\" rows."));
        $this->willSave(new ArrayClass([$this->grant("Order", AuthorizationType::update)]));
    }

    #[Test]
    public function thePermissionOfTypeAnyCoversEveryAction(): void
    {
        $this->fixUser("anyone");
        $this->setChanges(inserted: new Set([$this->order()]), updated: new Set([$this->order(changed: new Dictionary(["status" => "paid"]))]));
        $this->willSave(new ArrayClass([$this->grant("Order", AuthorizationType::any)]));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function theMirroredSideOfALinkNeedsNoUpdate(): void
    {
        $this->fixUser("linker");
        $customer = $this->customer();
        $order = $this->order(changed: new Dictionary(["customer" => $customer]));
        $this->setValues($customer, changed: new Dictionary(["orders" => new Set([$order])]));
        $this->setChanges(updated: new Set([$order, $customer]));
        $this->willSave(new ArrayClass([$this->grant("Order", AuthorizationType::update), $this->grant("Customer", AuthorizationType::read)]));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function theSideThatMadeTheLinkNeedsUpdate(): void
    {
        $this->fixUser("unlinker");
        $customer = $this->customer();
        $order = $this->order(changed: new Dictionary(["customer" => $customer]));
        $this->setValues($customer, changed: new Dictionary(["orders" => new Set([$order])]));
        $this->setChanges(updated: new Set([$order, $customer]));
        $this->expectExceptionObject(new ForbiddenException("You don't have permission to update \"Order\" rows."));
        $this->willSave(new ArrayClass([$this->grant("Customer", AuthorizationType::read)]));
    }

    #[Test]
    public function aToManyChangeNotMirroredElsewhereNeedsUpdate(): void
    {
        $this->fixUser("editor");
        $order = $this->order();
        $customer = $this->customer(changed: new Dictionary(["orders" => new Set([$order])]));
        $this->setChanges(updated: new Set([$customer]));
        $this->expectExceptionObject(new ForbiddenException("You don't have permission to update \"Customer\" rows."));
        $this->willSave(new ArrayClass([$this->grant("Order", AuthorizationType::read)]));
    }

    #[Test]
    public function aNullifiedRowNeedsUpdate(): void
    {
        $this->fixUser("nullifier");
        $customer = $this->customer();
        $order = $this->order(changed: new Dictionary(["customer" => Nil::nil()]), committed: new Dictionary(["customer" => $customer]));
        $this->setChanges(updated: new Set([$order]), deleted: new Set([$customer]));
        $this->expectExceptionObject(new ForbiddenException("You don't have permission to update \"Order\" rows."));
        $this->willSave(new ArrayClass([$this->grant("Customer", AuthorizationType::delete)]));
    }

    #[Test]
    public function linkingAnExistingRowNeedsReadOnItsEntity(): void
    {
        $this->fixUser("reader");
        $customer = $this->customer();
        $this->setChanges(inserted: new Set([$this->order(changed: new Dictionary(["customer" => $customer]), inserted: true)]));
        $this->expectExceptionObject(new ForbiddenException("You don't have permission to read \"Customer\"."));
        $this->willSave(new ArrayClass([$this->grant("Order", AuthorizationType::create)]));
    }

    #[Test]
    public function linkingAReadableRowPasses(): void
    {
        $this->fixUser("viewer");
        $customer = $this->customer();
        $this->setChanges(inserted: new Set([$this->order(changed: new Dictionary(["customer" => $customer]), inserted: true)]));
        $this->willSave(new ArrayClass([$this->grant("Order", AuthorizationType::create), $this->grant("Customer", AuthorizationType::read)]));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function linkingARowReadableHidesIsDenied(): void
    {
        $this->fixUser("outsider");
        $customer = $this->object(ObservedGuardedCustomerFixture::class, $this->entity("GuardedCustomer", ObservedGuardedCustomerFixture::class));
        $this->setChanges(inserted: new Set([$this->order(changed: new Dictionary(["customer" => $customer]), inserted: true)]));
        $this->expectExceptionObject(new ForbiddenException("You don't have permission to read \"GuardedCustomer\"."));
        $this->willSave(new ArrayClass([$this->grant("Order", AuthorizationType::create), $this->grant("GuardedCustomer", AuthorizationType::read)]));
    }

    #[Test]
    public function reassigningTheOwnerToTheSubjectDoesNotPassOwnership(): void
    {
        $subject = $this->user("thief");
        $this->fixRequestSecurityContext($this->restrictedRequestSecurityContext($subject, new ArrayClass(["Owned:update:own"]), $this->managedObjectContext));
        $row = $this->object(ObservedOwnedFixture::class, $this->entity("Owned", ObservedOwnedFixture::class), changed: new Dictionary(["createdBy" => $subject]), committed: new Dictionary(["createdBy" => $this->user("victim")]));
        $this->setChanges(updated: new Set([$row]));
        $this->expectExceptionObject(new ForbiddenException("You don't have permission to modify this \"Owned\" row: it belongs to another user."));
        $this->willSave(new ArrayClass());
    }

    #[Test]
    public function handingARowToAnotherUserIsDeniedUnderOwn(): void
    {
        $subject = $this->user("owner");
        $this->fixRequestSecurityContext($this->restrictedRequestSecurityContext($subject, new ArrayClass(["Owned:update:own"]), $this->managedObjectContext));
        $row = $this->object(ObservedOwnedFixture::class, $this->entity("Owned", ObservedOwnedFixture::class), changed: new Dictionary(["createdBy" => $heir = $this->user("heir")]), committed: new Dictionary(["createdBy" => $subject]));
        $row->createdBy = $heir;
        $this->setChanges(updated: new Set([$row]));
        $this->expectExceptionObject(new ForbiddenException("You don't have permission to modify this \"Owned\" row: it belongs to another user."));
        $this->willSave(new ArrayClass());
    }

    #[Test]
    public function aRowTheSubjectOwnsBeforeAndAfterTheSavePassesOwnership(): void
    {
        $subject = $this->user("keeper");
        $this->fixRequestSecurityContext($this->restrictedRequestSecurityContext($subject, new ArrayClass(["Owned:update:own"]), $this->managedObjectContext));
        $row = $this->object(ObservedOwnedFixture::class, $this->entity("Owned", ObservedOwnedFixture::class), changed: new Dictionary(["createdBy" => $subject]), committed: new Dictionary(["createdBy" => $subject]));
        $row->createdBy = $subject;
        $this->setChanges(updated: new Set([$row]));
        $this->willSave(new ArrayClass());
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function aNewRowOwnedByAnotherUserIsDeniedUnderOwn(): void
    {
        $this->fixRequestSecurityContext($this->restrictedRequestSecurityContext($this->user("forger"), new ArrayClass(["Owned:create:own"]), $this->managedObjectContext));
        $row = $this->object(ObservedOwnedFixture::class, $this->entity("Owned", ObservedOwnedFixture::class), changed: new Dictionary(["createdBy" => $victim = $this->user("victim")]), inserted: true);
        $row->createdBy = $victim;
        $this->setChanges(inserted: new Set([$row]));
        $this->expectExceptionObject(new ForbiddenException("You don't have permission to modify this \"Owned\" row: it belongs to another user."));
        $this->willSave(new ArrayClass());
    }

    #[Test]
    public function aNewRowOwnedByTheSubjectPassesUnderOwn(): void
    {
        $subject = $this->user("author");
        $this->fixRequestSecurityContext($this->restrictedRequestSecurityContext($subject, new ArrayClass(["Owned:create:own"]), $this->managedObjectContext));
        $row = $this->object(ObservedOwnedFixture::class, $this->entity("Owned", ObservedOwnedFixture::class), changed: new Dictionary(["createdBy" => $subject]), inserted: true);
        $row->createdBy = $subject;
        $this->setChanges(inserted: new Set([$row]));
        $this->willSave(new ArrayClass());
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function writableIsJudgedByTheCommittedValues(): void
    {
        $this->fixUser("closer");
        $row = $this->object(ObservedClosableFixture::class, $this->entity("Closable", ObservedClosableFixture::class), changed: new Dictionary(["status" => "closed"]), committed: new Dictionary(["status" => "open"]));
        $this->setChanges(updated: new Set([$row]));
        $this->willSave(new ArrayClass([$this->grant("Closable", AuthorizationType::update)]));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function aProtectedPropertyWrittenDirectlyIsDenied(): void
    {
        $this->fixUser("clerk");
        $row = $this->object(ObservedPricedFixture::class, $this->entity("Priced", ObservedPricedFixture::class), changed: new Dictionary(["price" => 9.5]), committed: new Dictionary(["price" => 1.0]));
        $this->setChanges(updated: new Set([$row]));
        $this->expectExceptionObject(new ForbiddenException("You don't have permission to modify \"price\" on \"Priced\"."));
        $this->willSave(new ArrayClass([$this->grant("Priced", AuthorizationType::update)]));
    }

    #[Test]
    public function aProtectedPropertyTheSubjectsRoleMayWritePasses(): void
    {
        $this->fixRequestSecurityContext($this->restrictedRequestSecurityContext($this->user("accountant", "Finance"), managedObjectContext: $this->managedObjectContext));
        $row = $this->object(ObservedPricedFixture::class, $this->entity("Priced", ObservedPricedFixture::class), changed: new Dictionary(["price" => 9.5]), committed: new Dictionary(["price" => 1.0]));
        $this->setChanges(updated: new Set([$row]));
        $this->willSave(new ArrayClass([$this->grant("Priced", AuthorizationType::update)]));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function aPropertyConditionIsJudgedByTheCommittedValues(): void
    {
        $this->fixUser("annotator");
        $row = $this->object(ObservedPricedFixture::class, $this->entity("Priced", ObservedPricedFixture::class), changed: new Dictionary(["note" => "x", "status" => "open"]), committed: new Dictionary(["status" => "locked"]));
        $this->setChanges(updated: new Set([$row]));
        $this->expectExceptionObject(new ForbiddenException("You don't have permission to modify \"note\" on \"Priced\"."));
        $this->willSave(new ArrayClass([$this->grant("Priced", AuthorizationType::update)]));
    }

    #[Test]
    public function aProtectedPropertyOnANewRowIsDenied(): void
    {
        $this->fixUser("inventor");
        $row = $this->object(ObservedPricedFixture::class, $this->entity("Priced", ObservedPricedFixture::class), changed: new Dictionary(["price" => 9.5]), inserted: true);
        $this->setChanges(inserted: new Set([$row]));
        $this->expectExceptionObject(new ForbiddenException("You don't have permission to modify \"price\" on \"Priced\"."));
        $this->willSave(new ArrayClass([$this->grant("Priced", AuthorizationType::create)]));
    }

    #[Test]
    public function aRowWritableExcludedBeforeTheChangeIsDenied(): void
    {
        $this->fixUser("reopener");
        $row = $this->object(ObservedClosableFixture::class, $this->entity("Closable", ObservedClosableFixture::class), changed: new Dictionary(["status" => "open"]), committed: new Dictionary(["status" => "closed"]));
        $this->setChanges(updated: new Set([$row]));
        $this->expectExceptionObject(new ForbiddenException("You don't have permission to modify this \"Closable\" resource."));
        $this->willSave(new ArrayClass([$this->grant("Closable", AuthorizationType::update)]));
    }

    /**
     * @param ArrayClass<CachedAuthorization> $authorizations
     */
    private function willSave(ArrayClass $authorizations): void
    {
        $cache = new InMemoryAuthorizationCache();
        $user = RequestSecurityContext::current()?->user;
        if ($user !== null) {
            $cache->setAuthorizableAuthorizations($user, $authorizations);
        }
        $resolver = new ReflectionClass(AuthorizationResolver::class)->newInstanceWithoutConstructor();
        new WriteAuthorizationObserver(new AuthorizationService($resolver, $cache))->contextWillSave(new Notification(ManagedObjectContextWillSave, $this->managedObjectContext));
    }

    private function fixUser(string $username): void
    {
        $this->fixRequestSecurityContext($this->restrictedRequestSecurityContext($this->user($username), managedObjectContext: $this->managedObjectContext));
    }

    private function grant(string $resource, AuthorizationType $type): CachedAuthorization
    {
        return new CachedAuthorization($resource, $type, AuthorizationScope::all);
    }

    /**
     * @param Set<ManagedObject>|null $inserted
     * @param Set<ManagedObject>|null $updated
     * @param Set<ManagedObject>|null $deleted
     */
    private function setChanges(?Set $inserted = null, ?Set $updated = null, ?Set $deleted = null): void
    {
        new ReflectionProperty(ManagedObjectContext::class, "insertedObjects")->setRawValue($this->managedObjectContext, $inserted ?? new Set());
        new ReflectionProperty(ManagedObjectContext::class, "updatedObjects")->setRawValue($this->managedObjectContext, $updated ?? new Set());
        new ReflectionProperty(ManagedObjectContext::class, "deletedObjects")->setRawValue($this->managedObjectContext, $deleted ?? new Set());
    }

    private function order(?Dictionary $changed = null, ?Dictionary $committed = null, bool $inserted = false): ObservedOrderFixture
    {
        /** @var ObservedOrderFixture */
        return $this->object(ObservedOrderFixture::class, $this->orderEntity, $changed, $committed, $inserted);
    }

    private function customer(?Dictionary $changed = null): ObservedCustomerFixture
    {
        /** @var ObservedCustomerFixture */
        return $this->object(ObservedCustomerFixture::class, $this->customerEntity, $changed);
    }

    private function relationship(string $name, bool $isToMany, string $inverseName): RelationshipDescription
    {
        $relationship = new ReflectionClass(RelationshipDescription::class)->newInstanceWithoutConstructor();
        $relationship->name = $name;
        $relationship->isToMany = $isToMany;
        $relationship->lazyInverseRelationshipName = $inverseName;
        return $relationship;
    }

    /**
     * @param class-string<ManagedObject> $className
     * @param Dictionary<RelationshipDescription>|null $relationships
     */
    private function entity(string $name, string $className, ?Dictionary $relationships = null): EntityDescription
    {
        $entity = new ReflectionClass(EntityDescription::class)->newInstanceWithoutConstructor();
        $entity->name = $name;
        $entity->managedObjectClassName = $className;
        new ReflectionProperty(EntityDescription::class, "relationshipsByName")->setRawValue($entity, $relationships ?? new Dictionary());
        return $entity;
    }

    /**
     * @param class-string<ManagedObject> $className
     */
    private function object(string $className, EntityDescription $entity, ?Dictionary $changed = null, ?Dictionary $committed = null, bool $inserted = false): ManagedObject
    {
        $object = new ReflectionClass($className)->newInstanceWithoutConstructor();
        new ReflectionProperty(ManagedObject::class, "entity")->setValue($object, $entity);
        new ReflectionProperty(ManagedObject::class, "managedObjectContext")->setValue($object, $this->managedObjectContext);
        $this->setValues($object, $changed ?? new Dictionary(), $committed ?? new Dictionary());
        new ReflectionProperty(ManagedObject::class, "isInserted")->setRawValue($object, $inserted);
        return $object;
    }

    private function setValues(ManagedObject $object, ?Dictionary $changed = null, ?Dictionary $committed = null): void
    {
        if ($changed !== null) {
            new ReflectionProperty(ManagedObject::class, "changedValues")->setRawValue($object, $changed);
        }
        if ($committed !== null) {
            new ReflectionProperty(ManagedObject::class, "fetchedCommittedValues")->setRawValue($object, $committed);
        }
        $keys = new ReflectionProperty(ManagedObject::class, "changedValues")->getRawValue($object)->keys->appendingContentsOf(new ReflectionProperty(ManagedObject::class, "fetchedCommittedValues")->getRawValue($object)->keys);
        new ReflectionProperty(ManagedObject::class, "persistentProperties")->setRawValue($object, $keys->reduce(new Dictionary(), function (Dictionary $carry, string $key): Dictionary {
            $carry[$key] = $key;
            return $carry;
        }));
    }

    private function role(string $name): AuthorizableRole
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

    private function user(string $username, string ...$roleNames): Authorizable
    {
        $roles = new Set(new ArrayClass($roleNames)->map(fn(string $name): AuthorizableRole => $this->role($name))->array);
        return new class($username, $roles) implements Authorizable {
            public function __construct(private readonly string $_username, private readonly Set $userRoles)
            {
            }
            public string $username {
                get => $this->_username;
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
}
