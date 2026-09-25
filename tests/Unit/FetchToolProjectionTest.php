<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Service\MCP\Schema\AttributeSchema;
use Sabatier\Service\MCP\Schema\EntitySchema;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Schema\ModelSchema;
use Sabatier\Service\MCP\Schema\PredicateGuideFactory;
use Sabatier\Service\MCP\Schema\RelationshipSchema;
use Sabatier\Service\MCP\Tools\FetchTool;

/**
 * Fixes the projection, sort and summary the tool derives from its arguments. Executing the fetch
 * is not exercised: that needs a store behind the context.
 */
final class FetchToolProjectionTest extends TestCase
{
    /** @throws ReflectionException */
    #[Test]
    public function theSummaryNamesTheEntityAndTheRowCount(): void
    {
        $summary = $this->invoke("buildSummary", "Order", new Dictionary(), 3);
        $this->assertStringContainsString("Fetched Order", $summary);
        $this->assertStringContainsString("3 row(s) returned", $summary);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theSummaryTellsTheCallerNotToRetry(): void
    {
        $this->assertStringContainsString("do not retry", $this->invoke("buildSummary", "Order", new Dictionary(), 0));
    }

    /** @throws ReflectionException */
    #[Test]
    public function theSummaryRepeatsThePredicateThatNarrowedIt(): void
    {
        $this->assertStringContainsString("filter: %K = %s", $this->invoke("buildSummary", "Order", new Dictionary(["predicate" => "%K = %s"]), 1));
    }

    /** @throws ReflectionException */
    #[Test]
    public function theSummarySpellsOutTheSortDirection(): void
    {
        $sort = new ArrayClass([new Dictionary(["key" => "total", "ascending" => false]), new Dictionary(["key" => "name"])]);
        $this->assertStringContainsString("sort: total DESC, name ASC", $this->invoke("buildSummary", "Order", new Dictionary(["sort" => $sort]), 1));
    }

    /** @throws ReflectionException */
    #[Test]
    public function anEmptySortIsLeftOutOfTheSummary(): void
    {
        $this->assertStringNotContainsString("sort:", $this->invoke("buildSummary", "Order", new Dictionary(["sort" => new ArrayClass()]), 1));
    }

    /** @throws ReflectionException */
    #[Test]
    public function aRequestWithoutAPredicateIsNotNarrowed(): void
    {
        $request = new FetchRequest();
        $this->invoke("applyPredicate", $request, "Order", new Dictionary());
        $this->assertNull($request->predicate);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aPredicateIsValidatedAndBuiltOntoTheRequest(): void
    {
        $request = new FetchRequest();
        $this->invoke("applyPredicate", $request, "Order", new Dictionary(["predicate" => "%K = %s", "arguments" => new ArrayClass(["total", "10"])]));
        $this->assertNotNull($request->predicate);
        $this->assertStringContainsString("total", $request->predicate->predicateFormat);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aPredicateNamingAnUnknownKeyPathIsRefused(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->invoke("applyPredicate", new FetchRequest(), "Order", new Dictionary(["predicate" => "%K = %s", "arguments" => new ArrayClass(["nope", "10"])]));
    }

    /** @throws ReflectionException */
    #[Test]
    public function aProjectionNamingKnownPropertiesIsAccepted(): void
    {
        $this->invoke("validateProjection", "Order", new Dictionary(["properties" => new ArrayClass(["total", "status"])]));
        $this->expectNotToPerformAssertions();
    }

    /** @throws ReflectionException */
    #[Test]
    public function aProjectionNamingAnUnknownPropertyIsRefused(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->invoke("validateProjection", "Order", new Dictionary(["properties" => new ArrayClass(["nope"])]));
    }

    /** @throws ReflectionException */
    #[Test]
    public function aProjectionMayReachIntoARelationship(): void
    {
        $this->invoke("validateProjection", "Order", new Dictionary(["relationships" => new Dictionary(["customer" => new ArrayClass(["name"])])]));
        $this->expectNotToPerformAssertions();
    }

    /** @throws ReflectionException */
    #[Test]
    public function aProjectionNamingAnUnknownRelationshipIsRefused(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->invoke("validateProjection", "Order", new Dictionary(["relationships" => new Dictionary(["nope" => new ArrayClass(["name"])])]));
    }

    /** @throws ReflectionException */
    #[Test]
    public function aProjectionNamingAnUnknownPropertyOfARelationshipIsRefused(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->invoke("validateProjection", "Order", new Dictionary(["relationships" => new Dictionary(["customer" => new ArrayClass(["nope"])])]));
    }

    /** @throws ReflectionException */
    #[Test]
    public function anExplicitShapeIsValidatedInPlaceOfThePropertyArguments(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->invoke("validateProjection", "Order", new Dictionary(["serialization" => new Dictionary(["nope" => true])]));
    }

    /** @throws ReflectionException */
    #[Test]
    public function anEmptyShapeFallsBackToThePropertyArguments(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->invoke("validateProjection", "Order", new Dictionary(["serialization" => new Dictionary(), "properties" => new ArrayClass(["nope"])]));
    }

    /** @throws ReflectionException */
    #[Test]
    public function aNestedShapeIsValidatedAgainstItsRelationshipTarget(): void
    {
        $this->invoke("validateShape", "Order", new Dictionary(["total" => true, "customer" => new Dictionary(["name" => true])]));
        $this->expectNotToPerformAssertions();
    }

    /** @throws ReflectionException */
    #[Test]
    public function nestingUnderSomethingThatIsNotARelationshipIsRefused(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->invoke("validateShape", "Order", new Dictionary(["total" => new Dictionary(["name" => true])]));
    }

    /** @throws ReflectionException */
    #[Test]
    public function anUnknownLeafInANestedShapeIsRefused(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->invoke("validateShape", "Order", new Dictionary(["customer" => new Dictionary(["nope" => true])]));
    }

    /** @throws ReflectionException */
    #[Test]
    public function aRelationshipIsAValidLeafOfAShape(): void
    {
        $this->invoke("validateShape", "Order", new Dictionary(["customer" => true]));
        $this->expectNotToPerformAssertions();
    }

    /** @throws ReflectionException */
    #[Test]
    public function noProjectionAtAllLeavesTheDefaultRepresentation(): void
    {
        $this->assertNull($this->invoke("resolveShape", new Dictionary()));
    }

    /** @throws ReflectionException */
    #[Test]
    public function anExplicitShapeIsPreferredOverThePropertyArguments(): void
    {
        $shape = $this->invoke("resolveShape", new Dictionary(["serialization" => new Dictionary(["total" => 1]), "properties" => new ArrayClass(["status"])]));
        $this->assertSame(["total" => true], $shape->array);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aShapeIsNormalizedSoEveryAttributeLeafIsTrue(): void
    {
        $shape = $this->invoke("normalizeShape", new Dictionary(["total" => "anything", "customer" => new Dictionary(["name" => 7])]));
        $this->assertTrue($shape["total"]);
        $this->assertSame(["name" => true], $shape["customer"]->array);
    }

    /** @throws ReflectionException */
    #[Test]
    public function thePropertyArgumentsBuildTheShapeWhenNoneWasGiven(): void
    {
        $shape = $this->invoke("resolveShape", new Dictionary(["properties" => new ArrayClass(["total", "status"])]));
        $this->assertSame(["total" => true, "status" => true], $shape->array);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theRelationshipArgumentsBecomeNestedSubShapes(): void
    {
        $shape = $this->invoke("resolveShape", new Dictionary(["relationships" => new Dictionary(["customer" => new ArrayClass(["name"])])]));
        $this->assertSame(["name" => true], $shape["customer"]->array);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aSortNamingKnownKeyPathsIsAccepted(): void
    {
        $this->invoke("validateSort", "Order", new ArrayClass([new Dictionary(["key" => "total"])]));
        $this->expectNotToPerformAssertions();
    }

    /** @throws ReflectionException */
    #[Test]
    public function aSortNamingAnUnknownKeyPathIsRefused(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->invoke("validateSort", "Order", new ArrayClass([new Dictionary(["key" => "nope"])]));
    }

    /** @throws ReflectionException */
    #[Test]
    public function aSortEntryWithoutAKeyIsSkipped(): void
    {
        $this->invoke("validateSort", "Order", new ArrayClass([new Dictionary(["ascending" => true])]));
        $this->expectNotToPerformAssertions();
    }

    /** @throws ReflectionException */
    #[Test]
    public function noSortAtAllIsAccepted(): void
    {
        $this->invoke("validateSort", "Order", null);
        $this->expectNotToPerformAssertions();
    }

    /** @throws ReflectionException */
    #[Test]
    public function aRequestWithoutASortKeepsItsOwnOrdering(): void
    {
        $request = new FetchRequest();
        $this->invoke("applySort", $request, null);
        $this->assertNull($request->sortDescriptors);
    }

    /** @throws ReflectionException */
    #[Test]
    public function sortEntriesBecomeSortDescriptorsDefaultingToAscending(): void
    {
        $request = new FetchRequest();
        $this->invoke("applySort", $request, new ArrayClass([new Dictionary(["key" => "total", "ascending" => false]), new Dictionary(["key" => "name"])]));
        /** @var SortDescriptor $first */
        $first = $request->sortDescriptors[0];
        $this->assertSame("total", $first->key);
        $this->assertFalse($first->ascending);
        /** @var SortDescriptor $second */
        $second = $request->sortDescriptors[1];
        $this->assertTrue($second->ascending);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aSortEntryWithoutAKeyContributesNoDescriptor(): void
    {
        $request = new FetchRequest();
        $this->invoke("applySort", $request, new ArrayClass([new Dictionary(["ascending" => true]), new Dictionary(["key" => "total"])]));
        $this->assertSame(1, $request->sortDescriptors?->count);
    }

    /** @throws ReflectionException */
    private function invoke(string $method, mixed ...$arguments): mixed
    {
        $descriptor = new ReflectionClass(ModelDescriptor::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(ModelDescriptor::class, "schema")->setRawValue($descriptor, new ModelSchema($this->entities(), new PredicateGuideFactory()->make()));
        $tool = new FetchTool(new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor(), $descriptor);
        return new ReflectionMethod(FetchTool::class, $method)->invoke($tool, ...$arguments);
    }

    /** @return Dictionary<EntitySchema> */
    private function entities(): Dictionary
    {
        $order = new EntitySchema("Order", "App\\Models\\Order", "Order", [], new Dictionary(["total" => new AttributeSchema("total", "float", true), "status" => new AttributeSchema("status", "string", true)]), new Dictionary(["customer" => new RelationshipSchema("customer", "Customer", false, true)]));
        $customer = new EntitySchema("Customer", "App\\Models\\Customer", "Customer", [], new Dictionary(["name" => new AttributeSchema("name", "string", true)]), new Dictionary());
        return new Dictionary(["Order" => $order, "Customer" => $customer]);
    }
}
