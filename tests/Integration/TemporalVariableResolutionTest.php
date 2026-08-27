<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
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
use Sabatier\Foundation\URL;
use Sabatier\Service\AccessConditionResolver;
use Sabatier\Service\AccessPolicy;
use Sabatier\Service\Application;
use Sabatier\Service\AuthorizationContext;
use Sabatier\Service\FieldLevelSecurityPolicy;
use Sabatier\Service\MCP\Schema\AttributeSchema;
use Sabatier\Service\MCP\Schema\EntitySchema;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Schema\ModelSchema;
use Sabatier\Service\MCP\Schema\PredicateGuide;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\AggregateTool;
use Sabatier\Service\MCP\Tools\CountTool;
use Sabatier\Service\PublicAccessPolicy;

/**
 * @property string $creationDate
 * @property int $amount
 */
final class TemporalVariableEntityFixture extends ManagedObject
{
}

/** Exercises the complete predicate path so a temporal token cannot silently reach Core Data as a literal string. */
final class TemporalVariableResolutionTest extends TestCase
{
    private const string entityName = "TemporalVariable";
    /** @var list<string> */
    private array $storePaths = [];
    private AccessPolicy $previousAccessPolicy;

    #[Override]
    protected function setUp(): void
    {
        $this->previousAccessPolicy = Application::shared()->accessPolicy;
        Application::shared()->accessPolicy = new PublicAccessPolicy();
    }

    #[Override]
    protected function tearDown(): void
    {
        Application::shared()->accessPolicy = $this->previousAccessPolicy;
        foreach ($this->storePaths as $storePath) {
            if (file_exists($storePath)) {
                unlink($storePath);
            }
        }
    }

    private function context(): ManagedObjectContext
    {
        $creationDate = new AttributeDescription();
        $creationDate->name = "creationDate";
        $creationDate->type = AttributeType::string;

        $amount = new AttributeDescription();
        $amount->name = "amount";
        $amount->type = AttributeType::integer32;

        $entity = new EntityDescription();
        $entity->name = self::entityName;
        $entity->managedObjectClassName = TemporalVariableEntityFixture::class;
        $entity->properties = new ArrayClass([$creationDate, $amount]);

        $model = new ManagedObjectModel();
        $model->entities = new ArrayClass([$entity]);
        $coordinator = new PersistentStoreCoordinator($model);
        $storePath = sys_get_temp_dir() . "/temporal-variable-resolution-" . uniqid("", true) . ".xml";
        $this->storePaths[] = $storePath;
        $storeURL = new URL("file:///" . str_replace("\\", "/", $storePath));
        $coordinator->addPersistentStoreWithType(PersistentStoreType::xml, null, $storeURL);

        $context = new ManagedObjectContext();
        $context->persistentStoreCoordinator = $coordinator;

        $matching = new TemporalVariableEntityFixture($context);
        $matching->creationDate = (string)(new AccessConditionResolver()->variables["\$WEEK_START"]);
        $matching->amount = 42;

        $other = new TemporalVariableEntityFixture($context);
        $other->creationDate = "2000-01-01";
        $other->amount = 100;
        $context->save();
        return $context;
    }

    private function descriptor(): ModelDescriptor
    {
        $attributes = new Dictionary([
            "creationDate" => new AttributeSchema("creationDate", "string", false),
            "amount" => new AttributeSchema("amount", "integer", false),
        ]);
        $entity = new EntitySchema(self::entityName, TemporalVariableEntityFixture::class, self::entityName, [], $attributes, new Dictionary());
        $schema = new ModelSchema(new Dictionary([self::entityName => $entity]), new PredicateGuide([], [], []));
        $descriptor = new ReflectionClass(ModelDescriptor::class)->newInstanceWithoutConstructor();
        new ReflectionClass(ModelDescriptor::class)->getProperty("schema")->setValue($descriptor, $schema);
        return $descriptor;
    }

    private function disableSecurity(AbstractTool $tool): void
    {
        $policy = new FieldLevelSecurityPolicy(new AuthorizationContext(null, new ArrayClass(), false));
        new ReflectionClass(AbstractTool::class)->getProperty("fieldSecurityPolicy")->setValue($tool, $policy);
    }

    /**
     * @param ArrayClass<ContentItem> $result
     * @return array<string, mixed>
     */
    private function decode(ArrayClass $result): array
    {
        $content = $result->first;
        $this->assertNotNull($content);
        /** @var array<string, mixed> */
        return json_decode($content->text, true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return Dictionary<mixed> */
    private function arguments(): Dictionary
    {
        return new Dictionary([
            "entity" => self::entityName,
            "predicate" => "%K == %@",
            "arguments" => new ArrayClass(["creationDate", "\$WEEK_START"]),
        ]);
    }

    #[Test]
    public function countResolvesTemporalPredicateArguments(): void
    {
        $tool = new CountTool($this->context(), $this->descriptor());
        $this->disableSecurity($tool);
        $this->assertSame(["count" => 1], $this->decode($tool->execute($this->arguments())));
    }

    #[Test]
    public function aggregateResolvesTemporalPredicateArguments(): void
    {
        $arguments = $this->arguments();
        $arguments["function"] = "median";
        $arguments["property"] = "amount";
        $tool = new AggregateTool($this->context(), $this->descriptor());
        $this->disableSecurity($tool);
        $decoded = $this->decode($tool->execute($arguments));
        $this->assertSame(42, $decoded["result"]);
    }
}
