<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\CoreData\PersistentStoreCoordinator;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Service\MCP\Schema\AttributeSchema;
use Sabatier\Service\MCP\Schema\EntitySchema;
use Sabatier\Service\MCP\Schema\EnumSchema;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Schema\ModelSchema;
use Sabatier\Service\MCP\Schema\PredicateGuideFactory;
use Sabatier\Service\MCP\Schema\RelationshipSchema;
use Sabatier\Service\MCP\Tools\WriteTool;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

enum SchemaToolStatusFixture: int
{
    case draft = 1;
    case paid = 2;
}

final class KeyPathProbeTool extends WriteTool
{
    #[Override]
    public string $name {
        get => "schema_probe";
    }
    #[Override]
    public array $inputSchema {
        get => ["type" => "object"];
    }

    #[Override]
    protected function executeCore(Dictionary $arguments): ArrayClass
    {
        return $this->textResult("ok");
    }

    public function exposedEntity(string $name): EntitySchema
    {
        return $this->entitySchema($name);
    }

    /** @param Dictionary<mixed> $values */
    public function exposedNormalizeRelationships(string $entityName, Dictionary $values): Dictionary
    {
        return $this->normalizeRelationships($entityName, $values);
    }

    /** @param Dictionary<mixed> $values */
    public function exposedShapeFromValues(string $entityName, Dictionary $values): Dictionary
    {
        return $this->shapeFromValues($entityName, $values);
    }
}

final class AbstractToolSchemaTest extends TestCase
{
    /** @throws ReflectionException */
    #[Test]
    public function resolvesAnEntityByName(): void
    {
        $this->assertSame("Order", $this->tool()->exposedEntity("Order")->name);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anUnknownEntityIsAFatalMisuse(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->tool()->exposedEntity("Ghost");
    }

    /** @throws ReflectionException */
    #[Test]
    public function aNumericRelationshipValueBecomesAnObjectIDReference(): void
    {
        $normalized = $this->tool()->exposedNormalizeRelationships("Order", new Dictionary(["customer" => 42, "total" => 10]));
        $this->assertInstanceOf(Dictionary::class, $normalized["customer"]);
        $this->assertSame(42, $normalized["customer"][ManagedObjectObjectIDKey]);
        $this->assertSame(10, $normalized["total"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aNonNumericRelationshipValueIsLeftAlone(): void
    {
        $values = new Dictionary(["customer" => new Dictionary(["name" => "Ada"])]);
        $this->assertSame($values["customer"], $this->tool()->exposedNormalizeRelationships("Order", $values)["customer"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function normalizingDoesNotMutateTheValuesItWasGiven(): void
    {
        $values = new Dictionary(["customer" => 42]);
        $this->tool()->exposedNormalizeRelationships("Order", $values);
        $this->assertSame(42, $values["customer"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anAttributeBecomesALeafOfTheShape(): void
    {
        $this->assertSame(["total" => true], $this->tool()->exposedShapeFromValues("Order", new Dictionary(["total" => 10]))->array);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aRelationshipWithNoNestedValuesIsALeafToo(): void
    {
        $this->assertSame(["customer" => true], $this->tool()->exposedShapeFromValues("Order", new Dictionary(["customer" => 42]))->array);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aNestedDictionaryIsRecursedInto(): void
    {
        $shape = $this->tool()->exposedShapeFromValues("Order", new Dictionary(["customer" => new Dictionary(["name" => "Ada"])]));
        $this->assertSame(["name" => true], $shape["customer"]->array);
    }

    /** @throws ReflectionException */
    #[Test]
    public function everyMemberOfAToManyRelationshipContributesToTheShape(): void
    {
        $shape = $this->tool()->exposedShapeFromValues("Order", new Dictionary(["lines" => new ArrayClass([new Dictionary(["name" => "a"]), new Dictionary(["total" => 1])])]));
        $this->assertSame(["name" => true, "total" => true], $shape["lines"]->array);
    }

    /** @throws ReflectionException */
    private function tool(?ManagedObjectContext $context = null): KeyPathProbeTool
    {
        $descriptor = new ReflectionClass(ModelDescriptor::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(ModelDescriptor::class, "schema")->setRawValue($descriptor, new ModelSchema($this->entities(), new PredicateGuideFactory()->make()));
        return new KeyPathProbeTool($context ?? new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor(), $descriptor);
    }

    /** @return Dictionary<EntitySchema> */
    private function entities(): Dictionary
    {
        $status = new AttributeSchema("status", "enum", true, enum: new EnumSchema(SchemaToolStatusFixture::class, new Dictionary(["draft" => 1, "paid" => 2])));
        $order = new EntitySchema("Order", "App\\Models\\Order", "Order", [], new Dictionary(["total" => new AttributeSchema("total", "float", true), "status" => $status]), new Dictionary(["customer" => new RelationshipSchema("customer", "Customer", false, true), "lines" => new RelationshipSchema("lines", "Customer", true, true)]));
        $customer = new EntitySchema("Customer", "App\\Models\\Customer", "Customer", [], new Dictionary(["name" => new AttributeSchema("name", "string", true), "total" => new AttributeSchema("total", "float", true)]), new Dictionary());
        return new Dictionary(["Order" => $order, "Customer" => $customer]);
    }
}
