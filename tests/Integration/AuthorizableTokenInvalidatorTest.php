<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Exception;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\CoreData\AttributeDescription;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\CoreData\PersistentStoreCoordinator;
use Sabatier\CoreData\PersistentStoreType;
use Sabatier\CoreData\RelationshipDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Notification;
use Sabatier\Foundation\NotificationCenter;
use Sabatier\Foundation\ObjectProtocol;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UUID;
use Sabatier\Service\Authorizable;
use Sabatier\Service\AuthorizableRole;
use Sabatier\Service\AuthorizableTokenInvalidator;
use Sabatier\Service\Authorization;
use Sabatier\Service\AuthorizationScope;
use Sabatier\Service\AuthorizationType;
use const Sabatier\CoreData\ManagedObjectContextDidSave;
use const Sabatier\CoreData\ManagedObjectContextWillSave;

/**
 * @property string $note
 * @method void addRolesObject(InvalidatorRoleFixture $object)
 * @method void removeRolesObject(InvalidatorRoleFixture $object)
 */
final class InvalidatorUserFixture extends ManagedObject implements Authorizable
{
    #[Override]
    public string $username {
        get => $this->valueForKey(__PROPERTY__);
        set {
            $this->setValueForKey($value, __PROPERTY__);
        }
    }
    #[Override]
    public ?string $password {
        get => null;
    }
    #[Override]
    public bool $isEnabled {
        get => true;
    }
    #[Override]
    public int $refreshTokenVersion {
        get => (int)$this->valueForKey(__PROPERTY__);
        set {
            $this->setValueForKey($value, __PROPERTY__);
        }
    }
    /** @var Set<InvalidatorRoleFixture> */
    #[Override]
    public Set $roles {
        get => $this->valueForKey(__PROPERTY__);
    }

    #[Override]
    public static function defaultRepresentation(): Dictionary
    {
        return new Dictionary();
    }
}

/**
 * @property int $position
 * @property Set<InvalidatorUserFixture> $users
 * @property Set<InvalidatorPermissionFixture> $permissions
 * @method void addPermissionsObject(InvalidatorPermissionFixture $object)
 */
final class InvalidatorRoleFixture extends ManagedObject implements AuthorizableRole
{
    #[Override]
    public string $name {
        get => $this->valueForKey(__PROPERTY__);
        set {
            $this->setValueForKey($value, __PROPERTY__);
        }
    }
    /** @var Set<Authorization> */
    #[Override]
    public Set $authorizations {
        get => $this->permissions;
    }
}

/**
 * @property Set<InvalidatorRoleFixture> $roles
 * @method void addRolesObject(InvalidatorRoleFixture $object)
 */
final class InvalidatorPermissionFixture extends ManagedObject implements Authorization
{
    #[Override]
    public string $name {
        get => $this->valueForKey(__PROPERTY__);
        set {
            $this->setValueForKey($value, __PROPERTY__);
        }
    }
    #[Override]
    public AuthorizationType $type {
        get => AuthorizationType::read;
    }
    #[Override]
    public AuthorizationScope $scope {
        get => AuthorizationScope::all;
    }
}

final class AuthorizableTokenInvalidatorTest extends TestCase
{
    private URL $storeURL;
    /** @var list<ObjectProtocol> */
    private array $observers = [];
    private ManagedObjectContext $context;
    private InvalidatorRoleFixture $sales;
    private InvalidatorRoleFixture $finance;
    private InvalidatorUserFixture $ana;
    private InvalidatorUserFixture $bruno;
    private InvalidatorUserFixture $carla;
    private InvalidatorPermissionFixture $orders;

    private static function attribute(string $name, AttributeType $type): AttributeDescription
    {
        $attribute = new AttributeDescription();
        $attribute->name = $name;
        $attribute->type = $type;
        $attribute->isOptional = true;
        return $attribute;
    }

    private static function relationship(string $name, string $destination, string $inverse): RelationshipDescription
    {
        $relationship = new RelationshipDescription();
        $relationship->name = $name;
        $relationship->lazyDestinationEntityName = $destination;
        $relationship->lazyInverseRelationshipName = $inverse;
        $relationship->isToMany = true;
        return $relationship;
    }

    /**
     * @param class-string<ManagedObject> $class
     * @param list<AttributeDescription|RelationshipDescription> $properties
     */
    private static function entity(string $name, string $class, array $properties): EntityDescription
    {
        $entity = new EntityDescription();
        $entity->name = $name;
        $entity->managedObjectClassName = $class;
        $entity->properties = new ArrayClass($properties);
        return $entity;
    }

    private static function model(): ManagedObjectModel
    {
        $model = new ManagedObjectModel();
        $model->entities = new ArrayClass([
            self::entity("InvalidatorUserFixture", InvalidatorUserFixture::class, [self::attribute("username", AttributeType::string), self::attribute("note", AttributeType::string), self::attribute("refreshTokenVersion", AttributeType::integer64), self::relationship("roles", "InvalidatorRoleFixture", "users")]),
            self::entity("InvalidatorRoleFixture", InvalidatorRoleFixture::class, [self::attribute("name", AttributeType::string), self::attribute("position", AttributeType::integer32), self::relationship("users", "InvalidatorUserFixture", "roles"), self::relationship("permissions", "InvalidatorPermissionFixture", "roles")]),
            self::entity("InvalidatorPermissionFixture", InvalidatorPermissionFixture::class, [self::attribute("name", AttributeType::string), self::relationship("roles", "InvalidatorRoleFixture", "permissions")]),
        ]);
        return $model;
    }

    /** @throws Exception */
    #[Override]
    protected function setUp(): void
    {
        $identifier = new UUID()->uuidString;
        $this->storeURL = FileManager::default()->temporaryDirectory->appendingPathComponent("authorizable-token-invalidator-$identifier.xml");
        $model = self::model();
        $coordinator = new PersistentStoreCoordinator($model);
        $coordinator->addPersistentStoreWithType(PersistentStoreType::xml, null, $this->storeURL);
        $this->context = new ManagedObjectContext();
        $this->context->persistentStoreCoordinator = $coordinator;

        $invalidator = new AuthorizableTokenInvalidator($model);
        $this->observers = [
            NotificationCenter::default()->addObserverForName(ManagedObjectContextWillSave, $this->context, fn(Notification $notification) => $invalidator->prepare($this->context)),
            NotificationCenter::default()->addObserverForName(ManagedObjectContextDidSave, $this->context, fn(Notification $notification) => $invalidator->invalidate($this->context)),
        ];

        $this->sales = new InvalidatorRoleFixture($this->context);
        $this->sales->name = "Sales";
        $this->finance = new InvalidatorRoleFixture($this->context);
        $this->finance->name = "Finance";
        $this->orders = new InvalidatorPermissionFixture($this->context);
        $this->orders->name = "orders";
        $this->orders->addRolesObject($this->sales);
        $this->ana = $this->user("ana", $this->sales);
        $this->bruno = $this->user("bruno", $this->sales);
        $this->carla = $this->user("carla", $this->finance);
        $this->context->save();
        $this->resetVersions();
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->observers as $observer) {
            NotificationCenter::default()->removeObserver($observer);
        }
        FileManager::default()->removeItem($this->storeURL);
    }

    private function user(string $username, InvalidatorRoleFixture $role): InvalidatorUserFixture
    {
        $user = new InvalidatorUserFixture($this->context);
        $user->username = $username;
        $user->refreshTokenVersion = 0;
        $user->addRolesObject($role);
        return $user;
    }

    /** @throws Exception */
    private function resetVersions(): void
    {
        foreach ([$this->ana, $this->bruno, $this->carla] as $user) {
            $user->refreshTokenVersion = 0;
        }
        $this->context->save();
    }

    /** @return array<string, int> */
    private function versions(): array
    {
        return ["ana" => $this->ana->refreshTokenVersion, "bruno" => $this->bruno->refreshTokenVersion, "carla" => $this->carla->refreshTokenVersion];
    }

    /** @throws Exception */
    #[Test]
    public function changingAnUnrelatedUserFieldInvalidatesNoOne(): void
    {
        $this->ana->note = "on leave";
        $this->context->save();
        $this->assertSame(["ana" => 0, "bruno" => 0, "carla" => 0], $this->versions());
    }

    /** @throws Exception */
    #[Test]
    public function grantingARoleInvalidatesOnlyThatUser(): void
    {
        $this->ana->addRolesObject($this->finance);
        $this->context->save();
        $this->assertSame(["ana" => 1, "bruno" => 0, "carla" => 0], $this->versions());
    }

    /** @throws Exception */
    #[Test]
    public function revokingARoleInvalidatesOnlyThatUser(): void
    {
        $this->bruno->removeRolesObject($this->sales);
        $this->context->save();
        $this->assertSame(["ana" => 0, "bruno" => 1, "carla" => 0], $this->versions());
    }

    /** @throws Exception */
    #[Test]
    public function grantingAPermissionToARoleInvalidatesItsMembers(): void
    {
        $invoices = new InvalidatorPermissionFixture($this->context);
        $invoices->name = "invoices";
        $this->sales->addPermissionsObject($invoices);
        $this->context->save();
        $this->assertSame(["ana" => 1, "bruno" => 1, "carla" => 0], $this->versions());
    }

    /** @throws Exception */
    #[Test]
    public function renamingAPermissionInvalidatesTheMembersOfItsRoles(): void
    {
        $this->orders->name = "sales orders";
        $this->context->save();
        $this->assertSame(["ana" => 1, "bruno" => 1, "carla" => 0], $this->versions());
    }

    /** @throws Exception */
    #[Test]
    public function resubmittingTheSameRolesAndRoleNamesInvalidatesNoOne(): void
    {
        $this->ana->setValueForKey(new Set([$this->sales]), "roles");
        $this->sales->name = "Sales";
        $this->context->save();
        $this->assertSame(["ana" => 0, "bruno" => 0, "carla" => 0], $this->versions());
    }

    /** @throws Exception */
    #[Test]
    public function resubmittingTheSamePermissionsInvalidatesNoOne(): void
    {
        $this->sales->setValueForKey(new Set([$this->orders]), "permissions");
        $this->context->save();
        $this->assertSame(["ana" => 0, "bruno" => 0, "carla" => 0], $this->versions());
    }

    /** @throws Exception */
    #[Test]
    public function renamingARoleInvalidatesItsMembers(): void
    {
        $this->finance->name = "Accounting";
        $this->context->save();
        $this->assertSame(["ana" => 0, "bruno" => 0, "carla" => 1], $this->versions());
    }

    /** @throws Exception */
    #[Test]
    public function changingMembershipFromTheRoleInvalidatesTheUsersAddedAndRemoved(): void
    {
        $this->sales->setValueForKey(new Set([$this->ana, $this->carla]), "users");
        $this->context->save();
        $this->assertSame(["ana" => 0, "bruno" => 1, "carla" => 1], $this->versions());
    }

    /** @throws Exception */
    #[Test]
    public function grantingAPermissionFromThePermissionInvalidatesTheRoleMembers(): void
    {
        $this->orders->setValueForKey(new Set([$this->sales, $this->finance]), "roles");
        $this->context->save();
        $this->assertSame(["ana" => 0, "bruno" => 0, "carla" => 1], $this->versions());
    }

    /** @throws Exception */
    #[Test]
    public function deletingARoleInvalidatesItsMembers(): void
    {
        $this->context->delete($this->finance);
        $this->context->save();
        $this->assertSame(["ana" => 0, "bruno" => 0, "carla" => 1], $this->versions());
    }

    /** @throws Exception */
    #[Test]
    public function deletingAPermissionInvalidatesTheMembersOfItsRoles(): void
    {
        $this->context->delete($this->orders);
        $this->context->save();
        $this->assertSame(["ana" => 1, "bruno" => 1, "carla" => 0], $this->versions());
    }

    /** @throws Exception */
    #[Test]
    public function insertingARoleWithMembersInvalidatesThem(): void
    {
        $auditors = new InvalidatorRoleFixture($this->context);
        $auditors->name = "Auditors";
        $auditors->setValueForKey(new Set([$this->carla]), "users");
        $this->context->save();
        $this->assertSame(["ana" => 0, "bruno" => 0, "carla" => 1], $this->versions());
    }

    /** @throws Exception */
    #[Test]
    public function changingAnUnrelatedRoleFieldInvalidatesNoOne(): void
    {
        $this->sales->position = 3;
        $this->context->save();
        $this->assertSame(["ana" => 0, "bruno" => 0, "carla" => 0], $this->versions());
    }
}
