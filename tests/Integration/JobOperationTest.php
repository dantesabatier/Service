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
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\CoreData\PersistentStoreCoordinator;
use Sabatier\CoreData\PersistentStoreType;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UUID;
use Sabatier\Service\Jobs\Job;
use Sabatier\Service\Jobs\JobOperation;

/** @property string $title */
final class JobRowFixture extends ManagedObject
{
}

final class InsertingJobFixture extends Job
{
    /** @throws Exception */
    #[Override]
    public function run(ManagedObjectContext $context): void
    {
        EntityDescription::insertNewObject("JobRow", $context)->setValueForKey("made by a job", "title");
    }
}

final class IdleJobFixture extends Job
{
    #[Override]
    public function run(ManagedObjectContext $context): void
    {
    }
}

final class JobOperationTest extends TestCase
{
    private URL $storeURL;
    private PersistentStoreCoordinator $coordinator;

    /** @throws Exception */
    #[Override]
    protected function setUp(): void
    {
        $title = new AttributeDescription();
        $title->name = "title";
        $title->type = AttributeType::string;
        $entity = new EntityDescription();
        $entity->name = "JobRow";
        $entity->managedObjectClassName = JobRowFixture::class;
        $entity->properties = new ArrayClass([$title]);
        $model = new ManagedObjectModel();
        $model->entities = new ArrayClass([$entity]);
        $this->coordinator = new PersistentStoreCoordinator($model);
        $identifier = new UUID()->uuidString;
        $this->storeURL = FileManager::default()->temporaryDirectory->appendingPathComponent("job-operation-$identifier.xml");
        $this->coordinator->addPersistentStoreWithType(PersistentStoreType::xml, null, $this->storeURL);
    }

    /** @throws Exception */
    #[Override]
    protected function tearDown(): void
    {
        FileManager::default()->removeItem($this->storeURL);
    }

    /** @throws Exception */
    #[Test]
    public function whatAJobChangesIsSavedUnderItsAuthor(): void
    {
        $context = $this->context();
        new JobOperation($context, new InsertingJobFixture(), "ada")->perform();
        $this->assertSame("ada", $context->transactionAuthor);
        $this->assertFalse($context->hasChanges);
        $this->assertSame(1, $this->context()->count($this->request()));
    }

    /** @throws Exception */
    #[Test]
    public function aJobThatChangesNothingSavesNothing(): void
    {
        new JobOperation($this->context(), new IdleJobFixture(), "system")->perform();
        $this->assertSame(0, $this->context()->count($this->request()));
    }

    private function context(): ManagedObjectContext
    {
        $context = new ManagedObjectContext();
        $context->persistentStoreCoordinator = $this->coordinator;
        return $context;
    }

    private function request(): FetchRequest
    {
        $request = new FetchRequest();
        $request->entity = $this->coordinator->managedObjectModel->entitiesByName["JobRow"];
        return $request;
    }
}
