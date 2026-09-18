<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
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
use Sabatier\Service\MCP\Tools\AbstractTool;
use TypeError;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

enum SchemaToolStatusFixture: int
{
    case draft = 1;
    case paid = 2;
}

final class KeyPathProbeTool extends AbstractTool
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
    public function execute(Dictionary $arguments): ArrayClass
    {
        return $this->textResult("ok");
    }

    public function exposedEntity(string $name): EntitySchema
    {
        return $this->entity($name);
    }

    public function exposedValidateKeyPath(string $entityName, string $keyPath): void
    {
        $this->validateKeyPath($entityName, $keyPath);
    }

    /** @param ArrayClass<mixed> $arguments */
    public function exposedValidatePredicateKeyPaths(string $entityName, string $format, ArrayClass $arguments): void
    {
        $this->validatePredicateKeyPaths($entityName, $format, $arguments);
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

    public function exposedResolveAttribute(string $entityName, string $keyPath): ?AttributeSchema
    {
        /** @var AttributeSchema|null */
        return new ReflectionMethod(AbstractTool::class, "resolveAttribute")->invoke($this, $entityName, $keyPath);
    }

    public function exposedFetchRequest(string $entityName): FetchRequest
    {
        return $this->fetchRequest($entityName);
    }
}

final class AbstractToolSchemaTest extends TestCase
{
    #[Test]
    public function resolvesAnEntityByName(): void
    {
        $this->assertSame("Order", $this->tool()->exposedEntity("Order")->name);
    }

    #[Test]
    public function anUnknownEntityIsAFatalMisuse(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->tool()->exposedEntity("Ghost");
    }

    #[Test]
    public function acceptsAnAttributeAsTheLastKeyPathComponent(): void
    {
        $this->tool()->exposedValidateKeyPath("Order", "total");
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function acceptsARelationshipAsTheLastKeyPathComponent(): void
    {
        $this->tool()->exposedValidateKeyPath("Order", "customer");
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function acceptsAKeyPathTraversingARelationship(): void
    {
        $this->tool()->exposedValidateKeyPath("Order", "customer.name");
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function rejectsAnUnknownFinalProperty(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->tool()->exposedValidateKeyPath("Order", "nope");
    }

    #[Test]
    public function rejectsTraversingSomethingThatIsNotARelationship(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->tool()->exposedValidateKeyPath("Order", "total.name");
    }

    #[Test]
    public function rejectsAnUnknownPropertyBeyondARelationship(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->tool()->exposedValidateKeyPath("Order", "customer.nope");
    }

    #[Test]
    public function acceptsAPredicateWhoseArgumentsMatchItsPlaceholders(): void
    {
        $this->tool()->exposedValidatePredicateKeyPaths("Order", "%K = %s", new ArrayClass(["total", "10"]));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function rejectsAPredicateWithTooFewArguments(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->tool()->exposedValidatePredicateKeyPaths("Order", "%K = %s", new ArrayClass(["total"]));
    }

    #[Test]
    public function rejectsAPredicateWithTooManyArguments(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->tool()->exposedValidatePredicateKeyPaths("Order", "%K = %s", new ArrayClass(["total", "10", "extra"]));
    }

    #[Test]
    public function everyKeyPathPlaceholderIsValidated(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->tool()->exposedValidatePredicateKeyPaths("Order", "%K = %s AND %K = %s", new ArrayClass(["total", "10", "nope", "x"]));
    }

    #[Test]
    public function aNonKeyPathPlaceholderIsNotTakenForAKeyPath(): void
    {
        $this->tool()->exposedValidatePredicateKeyPaths("Order", "%K = %s", new ArrayClass(["total", "nope"]));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function acceptsAMappedEnumValue(): void
    {
        $this->tool()->exposedValidatePredicateKeyPaths("Order", "%K = %d", new ArrayClass(["status", 2]));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function rejectsAnEnumCaseNameInPlaceOfItsValue(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->tool()->exposedValidatePredicateKeyPaths("Order", "%K = %d", new ArrayClass(["status", "paid"]));
    }

    #[Test]
    public function everyMemberOfAnEnumListIsChecked(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->tool()->exposedValidatePredicateKeyPaths("Order", "%K IN %@", new ArrayClass(["status", new ArrayClass([1, 99])]));
    }

    #[Test]
    public function theEnumRejectionNamesEveryCaseAgainstItsMappedValue(): void
    {
        try {
            $this->tool()->exposedValidatePredicateKeyPaths("Order", "%K = %d", new ArrayClass(["status", "paid"]));
            $this->fail("An unmapped enum value must be rejected.");
        } catch (InternalInconsistencyException $exception) {
            $reason = (string)$exception->error->localizedFailureReason;
            $this->assertStringContainsString("\"draft\" → 1", $reason);
            $this->assertStringContainsString("\"paid\" → 2", $reason);
        }
    }

    #[Test]
    public function anEnumListOfMappedValuesIsAccepted(): void
    {
        $this->tool()->exposedValidatePredicateKeyPaths("Order", "%K IN %@", new ArrayClass(["status", new ArrayClass([1, 2])]));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function aTrailingEnumKeyPathWithNoValueIsNotChecked(): void
    {
        $this->tool()->exposedValidatePredicateKeyPaths("Order", "%K", new ArrayClass(["status"]));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function aNonEnumAttributeAcceptsAnyValue(): void
    {
        $this->tool()->exposedValidatePredicateKeyPaths("Order", "%K = %s", new ArrayClass(["total", "anything"]));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function aNumericRelationshipValueBecomesAnObjectIDReference(): void
    {
        $normalized = $this->tool()->exposedNormalizeRelationships("Order", new Dictionary(["customer" => 42, "total" => 10]));
        $this->assertInstanceOf(Dictionary::class, $normalized["customer"]);
        $this->assertSame(42, $normalized["customer"][ManagedObjectObjectIDKey]);
        $this->assertSame(10, $normalized["total"]);
    }

    #[Test]
    public function aNonNumericRelationshipValueIsLeftAlone(): void
    {
        $values = new Dictionary(["customer" => new Dictionary(["name" => "Ada"])]);
        $this->assertSame($values["customer"], $this->tool()->exposedNormalizeRelationships("Order", $values)["customer"]);
    }

    #[Test]
    public function normalizingDoesNotMutateTheValuesItWasGiven(): void
    {
        $values = new Dictionary(["customer" => 42]);
        $this->tool()->exposedNormalizeRelationships("Order", $values);
        $this->assertSame(42, $values["customer"]);
    }

    #[Test]
    public function anAttributeBecomesALeafOfTheShape(): void
    {
        $this->assertSame(["total" => true], $this->tool()->exposedShapeFromValues("Order", new Dictionary(["total" => 10]))->array);
    }

    #[Test]
    public function aRelationshipWithNoNestedValuesIsALeafToo(): void
    {
        $this->assertSame(["customer" => true], $this->tool()->exposedShapeFromValues("Order", new Dictionary(["customer" => 42]))->array);
    }

    #[Test]
    public function aNestedDictionaryIsRecursedInto(): void
    {
        $shape = $this->tool()->exposedShapeFromValues("Order", new Dictionary(["customer" => new Dictionary(["name" => "Ada"])]));
        $this->assertSame(["name" => true], $shape["customer"]->array);
    }

    #[Test]
    public function everyMemberOfAToManyRelationshipContributesToTheShape(): void
    {
        $shape = $this->tool()->exposedShapeFromValues("Order", new Dictionary(["lines" => new ArrayClass([new Dictionary(["name" => "a"]), new Dictionary(["total" => 1])])]));
        $this->assertSame(["name" => true, "total" => true], $shape["lines"]->array);
    }

    #[Test]
    public function anAttributeIsResolvedThroughItsKeyPath(): void
    {
        $this->assertSame("total", $this->tool()->exposedResolveAttribute("Order", "total")?->name);
    }

    #[Test]
    public function anAttributeBeyondARelationshipIsResolvedToo(): void
    {
        $this->assertSame("name", $this->tool()->exposedResolveAttribute("Order", "customer.name")?->name);
    }

    #[Test]
    public function aRelationshipIsNotAnAttribute(): void
    {
        $this->assertNull($this->tool()->exposedResolveAttribute("Order", "customer"));
    }

    #[Test]
    public function anUnknownPropertyResolvesToNoAttribute(): void
    {
        $this->assertNull($this->tool()->exposedResolveAttribute("Order", "nope"));
    }

    #[Test]
    public function traversingSomethingThatIsNotARelationshipResolvesToNothing(): void
    {
        $this->assertNull($this->tool()->exposedResolveAttribute("Order", "total.name"));
    }

    #[Test]
    public function aFetchRequestIsScopedToTheNamedEntity(): void
    {
        $this->assertSame("Order", $this->toolWithModel()->exposedFetchRequest("Order")->entity?->name);
    }

    #[Test]
    public function aFetchRequestForAnUnknownEntityIsRefused(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->toolWithModel()->exposedFetchRequest("Ghost");
    }

    #[Test]
    public function aFetchRequestWithoutAModelBehindTheContextIsRefused(): void
    {
        try {
            $this->tool()->exposedFetchRequest("Order");
            $this->fail("A context with no model must be refused.");
        } catch (InternalInconsistencyException $exception) {
            // A missing model and an unknown entity both raise, so the message is what tells them apart.
            $this->assertStringContainsString("No managed object model", (string)$exception->error->localizedFailureReason);
        }
    }

    private function toolWithModel(): KeyPathProbeTool
    {
        $entity = new EntityDescription();
        $entity->name = "Order";
        $entity->properties = new ArrayClass();
        $model = new ManagedObjectModel();
        $model->entities = new ArrayClass([$entity]);
        $context = new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
        $context->persistentStoreCoordinator = new PersistentStoreCoordinator($model);
        return $this->tool($context);
    }

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
