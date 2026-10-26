<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Exception;
use JsonException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Sabatier\CoreData\AttributeDescription;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\CoreData\PersistentStoreCoordinator;
use Sabatier\CoreData\PersistentStoreType;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UUID;
use Sabatier\Service\AccessPolicy;
use Sabatier\Service\Application;
use Sabatier\Service\AuthorizationContext;
use Sabatier\Service\FieldLevelSecurityPolicy;
use Sabatier\Service\MCP\Schema\AttributeSchema;
use Sabatier\Service\MCP\Schema\EntitySchema;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Schema\ModelSchema;
use Sabatier\Service\MCP\Schema\PredicateGuide;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\FetchTool;
use Sabatier\Service\PublicAccessPolicy;

/**
 * @property int $n
 * @property string $label
 */
final class FetchToolBatchEntityFixture extends ManagedObject
{
}

/**
 * Fixes that reading in batches still delivers the whole result, in order. That each batch releases
 * the previous one cannot be seen here: the XML store registers every row it loads.
 */
final class FetchToolBatchTest extends TestCase
{
    private const string entityName = "FetchToolBatch";
    private const int batchSize = 200;
    private URL $storeURL;
    private AccessPolicy $previousAccessPolicy;

    #[Override]
    protected function setUp(): void
    {
        $this->previousAccessPolicy = Application::shared()->accessPolicy;
        Application::shared()->accessPolicy = new PublicAccessPolicy();
        $this->storeURL = FileManager::default()->temporaryDirectory
            ->appendingPathComponent(new UUID()->uuidString)
            ->appendingPathExtension("xml");
    }

    /** @throws Exception */
    #[Override]
    protected function tearDown(): void
    {
        Application::shared()->accessPolicy = $this->previousAccessPolicy;
        FileManager::default()->removeItem($this->storeURL);
    }

    private static function model(): ManagedObjectModel
    {
        $n = new AttributeDescription();
        $n->name = "n";
        $n->type = AttributeType::integer32;

        $label = new AttributeDescription();
        $label->name = "label";
        $label->type = AttributeType::string;

        $entity = new EntityDescription();
        $entity->name = self::entityName;
        $entity->managedObjectClassName = FetchToolBatchEntityFixture::class;
        $entity->properties = new ArrayClass([$n, $label]);

        $model = new ManagedObjectModel();
        $model->entities = new ArrayClass([$entity]);
        return $model;
    }

    /** @throws Exception */
    private function context(): ManagedObjectContext
    {
        $coordinator = new PersistentStoreCoordinator(self::model());
        $coordinator->addPersistentStoreWithType(PersistentStoreType::xml, null, $this->storeURL);
        $context = new ManagedObjectContext();
        $context->persistentStoreCoordinator = $coordinator;
        return $context;
    }

    /**
     * Seeds $count rows numbered 0..$count-1, from a context of their own.
     *
     * @throws Exception
     */
    private function seed(int $count): void
    {
        $context = $this->context();
        for ($i = 0; $i < $count; $i++) {
            $row = new FetchToolBatchEntityFixture($context);
            $row->n = $i;
            $row->label = "row $i";
        }
        $context->save();
    }

    /** @throws ReflectionException */
    private function descriptor(): ModelDescriptor
    {
        $attributes = new Dictionary([
            "n" => new AttributeSchema("n", "integer", false),
            "label" => new AttributeSchema("label", "string", false),
        ]);
        $entity = new EntitySchema(self::entityName, FetchToolBatchEntityFixture::class, self::entityName, [], $attributes, new Dictionary());
        $schema = new ModelSchema(new Dictionary([self::entityName => $entity]), new PredicateGuide([], [], []));
        $descriptor = new ReflectionClass(ModelDescriptor::class)->newInstanceWithoutConstructor();
        new ReflectionClass(ModelDescriptor::class)->getProperty("schema")->setValue($descriptor, $schema);
        return $descriptor;
    }

    /** @throws ReflectionException */
    private function tool(ManagedObjectContext $context): FetchTool
    {
        $tool = new FetchTool($context, $this->descriptor());
        $policy = new FieldLevelSecurityPolicy(new AuthorizationContext(null, new ArrayClass(), false));
        new ReflectionClass(AbstractTool::class)->getProperty("fieldSecurityPolicy")->setValue($tool, $policy);
        return $tool;
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     * @throws JsonException
     */
    private function fetch(ManagedObjectContext $context): array
    {
        $arguments = new Dictionary([
            "entityName" => self::entityName,
            "serialization" => new Dictionary(["n" => true, "label" => true]),
            "sortDescriptors" => new ArrayClass([new Dictionary(["key" => "n", "ascending" => true])]),
            "fetchBatchSize" => self::batchSize,
        ]);
        $content = $this->tool($context)->call($arguments)->first;
        $this->assertNotNull($content);
        /** @var array<string, mixed> */
        return json_decode($content->text, true, flags: JSON_THROW_ON_ERROR);
    }

    /** @throws Exception */
    #[Test]
    public function everyRowArrivesOnceAndInOrderAcrossTheBatches(): void
    {
        $count = self::batchSize * 2 + 50;
        $this->seed($count);
        $decoded = $this->fetch($this->context());
        $this->assertSame($count, $decoded["rowCount"]);
        /** @var list<array{n: int, label: string}> $results */
        $results = $decoded["results"];
        $this->assertSame(range(0, $count - 1), new ArrayClass($results)->map(fn(array $row): int => $row["n"])->array);
        $this->assertSame("row " . ($count - 1), $results[$count - 1]["label"]);
    }
}
