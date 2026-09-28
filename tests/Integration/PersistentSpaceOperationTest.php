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
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\CoreData\PersistentStoreCoordinator;
use Sabatier\CoreData\PersistentStoreType;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UUID;
use Sabatier\Service\Authorizable;
use Sabatier\Service\AuthorizableRole;
use Sabatier\Service\AuthorizationContext;
use Sabatier\Service\CountPersistentSpaceOperation;
use Sabatier\Service\FetchPersistentSpaceOperation;
use Sabatier\Service\FieldLevelSecurityPolicy;
use Sabatier\Service\FieldSecurityPolicy;
use Sabatier\Service\ReadPersistentSpaceOperation;
use Sabatier\Service\Readable;

#[Readable(where: "status == %@", arguments: ["open"])]
final class OperationRowFixture extends ManagedObject
{
    public string $status {
        get => $this->valueForKey(__PROPERTY__);
        set {
            $this->setValueForKey($value, __PROPERTY__);
        }
    }
    #[Readable(["auditor"])]
    public string $note {
        get => $this->valueForKey(__PROPERTY__);
        set {
            $this->setValueForKey($value, __PROPERTY__);
        }
    }
}

final readonly class RecordingFieldSecurityPolicy extends FieldSecurityPolicy
{
    /** @var ArrayClass<ManagedObject> */
    public ArrayClass $reads;

    public function __construct(AuthorizationContext $authorizationContext)
    {
        parent::__construct($authorizationContext);
        $this->reads = new ArrayClass();
    }

    #[Override]
    public function applySecureUpdate(ManagedObject $object, Dictionary $body): void
    {
    }

    #[Override]
    public function applySecureRead(ManagedObject $object, Dictionary $data): Dictionary
    {
        $this->reads->append($object);
        return $data;
    }
}

final class PersistentSpaceOperationTest extends TestCase
{
    private const string entityName = "OperationRow";
    private URL $storeURL;
    private ManagedObjectContext $context;

    /** @throws Exception */
    #[Override]
    protected function setUp(): void
    {
        $status = new AttributeDescription();
        $status->name = "status";
        $status->type = AttributeType::string;
        $note = new AttributeDescription();
        $note->name = "note";
        $note->type = AttributeType::string;
        $entity = new EntityDescription();
        $entity->name = self::entityName;
        $entity->managedObjectClassName = OperationRowFixture::class;
        $entity->properties = new ArrayClass([$status, $note]);
        $model = new ManagedObjectModel();
        $model->entities = new ArrayClass([$entity]);
        $coordinator = new PersistentStoreCoordinator($model);
        $identifier = new UUID()->uuidString;
        $this->storeURL = FileManager::default()->temporaryDirectory->appendingPathComponent("persistent-space-operation-$identifier.xml");
        $coordinator->addPersistentStoreWithType(PersistentStoreType::xml, null, $this->storeURL);
        $this->context = new ManagedObjectContext();
        $this->context->persistentStoreCoordinator = $coordinator;
        $open = new OperationRowFixture($this->context);
        $open->status = "open";
        $open->note = "visible to auditors";
        $closed = new OperationRowFixture($this->context);
        $closed->status = "closed";
        $closed->note = "never read";
        $this->context->save();
    }

    /** @throws Exception */
    #[Override]
    protected function tearDown(): void
    {
        FileManager::default()->removeItem($this->storeURL);
    }

    /** @throws Exception */
    #[Test]
    public function aReadIsNarrowedByTheResourceRule(): void
    {
        $rows = new ReadPersistentSpaceOperation($this->context, $this->policy(true), $this->request())->perform();
        $this->assertSame(1, $rows->count);
        $this->assertSame("open", $rows->first?->valueForKey("status"));
    }

    /** @throws Exception */
    #[Test]
    public function aFieldTheSubjectMayNotReadIsLeftOutOfEveryRow(): void
    {
        $this->assertFalse(new ReadPersistentSpaceOperation($this->context, $this->policy(true), $this->request())->perform()->first?->offsetExists("note"));
        $this->assertTrue(new ReadPersistentSpaceOperation($this->context, $this->policy(true, "auditor"), $this->request())->perform()->first?->offsetExists("note"));
    }

    /** @throws Exception */
    #[Test]
    public function withSecurityDisabledEveryRowAndFieldIsRead(): void
    {
        $rows = new ReadPersistentSpaceOperation($this->context, $this->policy(false), $this->request())->perform();
        $this->assertSame(2, $rows->count);
        $this->assertTrue($rows->first?->offsetExists("note"));
    }

    /** @throws Exception */
    #[Test]
    public function aRowIsFilteredWhenItIsReadAndNotBefore(): void
    {
        $policy = new RecordingFieldSecurityPolicy(new AuthorizationContext($this->user(), new ArrayClass(), false));
        $rows = new ReadPersistentSpaceOperation($this->context, $policy, $this->request())->perform();
        $this->assertTrue($policy->reads->isEmpty);
        $rows->first;
        $this->assertSame(1, $policy->reads->count);
    }

    /** @throws Exception */
    #[Test]
    public function aBatchedReadIsFilteredRowByRow(): void
    {
        $request = $this->request();
        $request->fetchBatchSize = 1;
        $rows = new ReadPersistentSpaceOperation($this->context, $this->policy(true), $request)->perform();
        $this->assertSame(1, $rows->count);
        foreach ($rows as $row) {
            $this->assertInstanceOf(Dictionary::class, $row);
            $this->assertSame("open", $row["status"]);
            $this->assertFalse($row->offsetExists("note"));
        }
    }

    /** @throws Exception */
    #[Test]
    public function objectIDsAreNarrowedAndReadAsTheyCome(): void
    {
        $request = $this->request();
        $request->resultType = FetchRequestResultType::managedObjectIDResultType;
        $rows = new ReadPersistentSpaceOperation($this->context, $this->policy(true), $request)->perform();
        $this->assertSame(1, $rows->count);
        $this->assertInstanceOf(ManagedObjectID::class, $rows->first);
    }

    /** @throws Exception */
    #[Test]
    public function dictionaryRowsAreNarrowedButNotFiltered(): void
    {
        $request = $this->request();
        $request->resultType = FetchRequestResultType::dictionaryResultType;
        $request->propertiesToFetch = new ArrayClass(["status", "note"]);
        $rows = new ReadPersistentSpaceOperation($this->context, $this->policy(true), $request)->perform();
        $this->assertSame(1, $rows->count);
        $this->assertSame("visible to auditors", $rows->first?->valueForKey("note"));
    }

    /** @throws Exception */
    #[Test]
    public function aCountIsNarrowedByTheResourceRule(): void
    {
        $this->assertSame(1, new CountPersistentSpaceOperation($this->context, $this->policy(true), $this->request())->perform());
        $this->assertSame(2, new CountPersistentSpaceOperation($this->context, $this->policy(false), $this->request())->perform());
    }

    /** @throws Exception */
    #[Test]
    public function objectsFetchedToComputeOverAreNarrowedButNotSerialized(): void
    {
        $objects = new FetchPersistentSpaceOperation($this->context, $this->policy(true), $this->request())->perform();
        $this->assertSame(1, $objects->count);
        $this->assertInstanceOf(OperationRowFixture::class, $objects->first);
    }

    /** @throws Exception */
    #[Test]
    public function theRowsOfAReadCannotBeChanged(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        new ReadPersistentSpaceOperation($this->context, $this->policy(true), $this->request())->perform()->append(new Dictionary());
    }

    private function request(): FetchRequest
    {
        $request = new FetchRequest();
        $request->entity = $this->context->persistentStoreCoordinator?->managedObjectModel?->entitiesByName[self::entityName];
        return $request;
    }

    /** @noinspection PhpSameParameterValueInspection */
    private function policy(bool $isSecurityEnabled, string ...$roleNames): FieldLevelSecurityPolicy
    {
        return new FieldLevelSecurityPolicy(new AuthorizationContext($this->user(...$roleNames), new ArrayClass(), $isSecurityEnabled));
    }

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
