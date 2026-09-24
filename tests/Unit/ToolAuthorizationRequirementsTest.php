<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Service\MCP\Schema\AttributeSchema;
use Sabatier\Service\MCP\Schema\EntitySchema;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Schema\ModelSchema;
use Sabatier\Service\MCP\Schema\PredicateGuideFactory;
use Sabatier\Service\MCP\Schema\RelationshipSchema;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\AggregateTool;
use Sabatier\Service\MCP\Tools\AuthorizationRequirement;
use Sabatier\Service\MCP\Tools\CountTool;
use Sabatier\Service\MCP\Tools\CreateTool;
use Sabatier\Service\MCP\Tools\DeleteTool;
use Sabatier\Service\MCP\Tools\DescribeModelTool;
use Sabatier\Service\MCP\Tools\FetchTool;
use Sabatier\Service\MCP\Tools\GetServerTimeTool;
use Sabatier\Service\MCP\Tools\GroupByTool;
use Sabatier\Service\MCP\Tools\JobTool;
use Sabatier\Service\MCP\Tools\PersistentHistoryTool;
use Sabatier\Service\MCP\Tools\PredicateKeyPathCollector;
use Sabatier\Service\MCP\Tools\UpdateTool;
use Sabatier\Service\MCP\Tools\WebSearchTool;

final class ToolAuthorizationRequirementsTest extends TestCase
{
    #[Test]
    public function aFetchReadsEveryEntityItsProjectionPredicateAndSortReach(): void
    {
        $arguments = new Dictionary([
            "entity" => "Order",
            "serialization" => new Dictionary(["total" => true, "customer" => new Dictionary(["area" => new Dictionary(["name" => true])])]),
            "predicate" => "ANY lines.sku == %@ AND SUBQUERY(items, \$x, \$x.product.price > 3).@count > 0",
            "arguments" => new ArrayClass(["A"]),
            "sort" => new ArrayClass([new Dictionary(["key" => "customer.name"])]),
        ]);
        $this->assertSame(["Order:read", "Customer:read", "Area:read", "Line:read", "Item:read", "Product:read"], $this->keys(FetchTool::class, $arguments));
    }

    #[Test]
    public function aFetchWithTheShallowIncludeReadsItsRelationships(): void
    {
        $arguments = new Dictionary(["entity" => "Order", "properties" => new ArrayClass(["total"]), "relationships" => new Dictionary(["customer" => new ArrayClass(["name"])])]);
        $this->assertSame(["Order:read", "Customer:read"], $this->keys(FetchTool::class, $arguments));
    }

    #[Test]
    public function theDeclarationDoesNotValidateThePredicateAgainstTheSchema(): void
    {
        foreach ([FetchTool::class, CountTool::class] as $className) {
            $arguments = new Dictionary(["entity" => "Order", "predicate" => "%K = 1", "arguments" => new ArrayClass(["secret"])]);
            $this->assertSame(["Order:read"], $this->keys($className, $arguments), $className);
        }
    }

    #[Test]
    public function aPlainFetchReadsOnlyItsEntity(): void
    {
        $this->assertSame(["Order:read"], $this->keys(FetchTool::class, new Dictionary(["entity" => "Order"])));
    }

    #[Test]
    public function aCountReadsTheEntitiesItsPredicateCrosses(): void
    {
        $arguments = new Dictionary(["entity" => "Order", "predicate" => "%K == %@", "arguments" => new ArrayClass(["customer.area.name", "North"])]);
        $this->assertSame(["Order:read", "Customer:read", "Area:read"], $this->keys(CountTool::class, $arguments));
    }

    #[Test]
    public function anAggregateReadsTheEntitiesItsPropertyCrosses(): void
    {
        $arguments = new Dictionary(["entity" => "Order", "function" => "sum", "property" => "customer.credit"]);
        $this->assertSame(["Order:read", "Customer:read"], $this->keys(AggregateTool::class, $arguments));
    }

    #[Test]
    public function aGroupingReadsTheEntitiesItsKeysAndAggregatesCross(): void
    {
        $arguments = new Dictionary(["entity" => "Order", "group_by" => new ArrayClass(["customer.area.name"]), "aggregates" => new ArrayClass([new Dictionary(["function" => "sum", "property" => "items.quantity", "as" => "units"])])]);
        $this->assertSame(["Order:read", "Customer:read", "Area:read", "Item:read"], $this->keys(GroupByTool::class, $arguments));
    }

    #[Test]
    public function aGroupingReadsTheEntitiesItsHavingAndSortCross(): void
    {
        $arguments = new Dictionary(["entity" => "Order", "group_by" => new ArrayClass(["total"]), "aggregates" => new ArrayClass([new Dictionary(["function" => "count", "property" => "objectID", "as" => "orders"])]),
            "having_predicate" => "%K > %d AND %K > %d", "having_arguments" => new ArrayClass(["orders", 1, "customer.credit", 100]),
            "sort" => new ArrayClass([new Dictionary(["key" => "items.quantity"]), new Dictionary(["key" => "orders"])])]);
        $this->assertSame(["Order:read", "Customer:read", "Item:read"], $this->keys(GroupByTool::class, $arguments));
    }

    #[Test]
    public function theCollectorReachesKeyPathsInsideFunctionsOperatorsAndNestedSubqueries(): void
    {
        $cases = [
            "FUNCTION(customer.name, \"uppercaseString\") == \"A\"" => "customer.name",
            "sum:(items.quantity) > 3" => "items.quantity",
            "items.@sum.quantity > 3" => "items",
            "TERNARY(customer.credit > 0, total, 0) > 3" => "customer.credit",
            "SUBQUERY(items, \$x, SUBQUERY(\$x.product.parts, \$y, \$y.vendor.name == \"a\").@count > 0).@count > 0" => "items.product.parts.vendor.name",
            "SUBQUERY(customer.orders, \$x, TRUEPREDICATE).@count > 0" => "customer.orders",
            "SUBQUERY(items, \$x, SUBQUERY(\$x.product.parts, \$y, TRUEPREDICATE).@count > 0).@count > 0" => "items.product.parts",
        ];
        foreach ($cases as $format => $keyPath) {
            /** @var Predicate $predicate */
            $predicate = Predicate::format($format, new ArrayClass());
            $this->assertTrue(PredicateKeyPathCollector::keyPaths($predicate)->containsElement($keyPath), $format);
        }
    }

    #[Test]
    public function aCreateCoversEveryRowItsValuesName(): void
    {
        $arguments = new Dictionary(["entity" => "Order", "values" => new Dictionary([
            "total" => 10,
            "customer" => 42,
            "items" => new ArrayClass([
                new Dictionary(["quantity" => 1, "product" => new Dictionary(["objectID" => 7])]),
                new Dictionary(["objectID" => 5]),
                new Dictionary(["objectID" => 6, "quantity" => 2]),
            ]),
        ])]);
        $this->assertSame(["Order:create", "Customer:read", "Item:create", "Product:read", "Item:read", "Item:update"], $this->keys(CreateTool::class, $arguments));
    }

    #[Test]
    public function anUpdateNeedsUpdateOnItsEntityAndCoversItsNestedRows(): void
    {
        $arguments = new Dictionary(["entity" => "Order", "objectID" => 1, "values" => new Dictionary(["customer" => new Dictionary(["objectID" => 42, "name" => "Acme"])])]);
        $this->assertSame(["Order:update", "Customer:update"], $this->keys(UpdateTool::class, $arguments));
    }

    #[Test]
    public function aNestedRowNamingAConcreteEntityIsAuthorizedAgainstIt(): void
    {
        $arguments = new Dictionary(["entity" => "Order", "objectID" => 1, "values" => new Dictionary(["customer" => new Dictionary(["entityName" => "Company", "name" => "Acme"])])]);
        $this->assertSame(["Order:update", "Company:create"], $this->keys(UpdateTool::class, $arguments));
    }

    #[Test]
    public function aRowLinkedThroughAnAbstractTargetMustNameItsConcreteEntity(): void
    {
        foreach ([42, new Dictionary(["objectID" => 42]), new Dictionary(["objectID" => 42, "name" => "Acme"])] as $member) {
            $arguments = new Dictionary(["entity" => "Order", "objectID" => 1, "values" => new Dictionary(["party" => $member])]);
            try {
                $this->tool(UpdateTool::class)->authorizationRequirements($arguments);
                $this->fail("An abstract target without entityName was authorized.");
            } catch (InternalInconsistencyException $exception) {
                $this->assertStringContainsString("\"Party\" is an abstract entity", $exception->error->localizedFailureReason ?? "");
            }
        }
        $arguments = new Dictionary(["entity" => "Order", "objectID" => 1, "values" => new Dictionary(["party" => new Dictionary(["objectID" => 42, "entityName" => "Company"])])]);
        $this->assertSame(["Order:update", "Company:read"], $this->keys(UpdateTool::class, $arguments));
    }

    #[Test]
    public function aDeleteNeedsDeleteOnItsEntity(): void
    {
        $this->assertSame(["Order:delete"], $this->keys(DeleteTool::class, new Dictionary(["entity" => "Order", "objectID" => 1])));
    }

    #[Test]
    public function runningAJobNeedsAnyOnJobs(): void
    {
        $this->assertSame(["Jobs:any"], $this->keys(JobTool::class, new Dictionary(["job" => "Anything"])));
    }

    #[Test]
    public function historyNeedsReadToFetchAndDeleteToPurge(): void
    {
        $this->assertSame(["history:read"], $this->keys(PersistentHistoryTool::class, new Dictionary(["operation" => "fetch"])));
        $this->assertSame(["history:delete"], $this->keys(PersistentHistoryTool::class, new Dictionary(["operation" => "purge"])));
    }

    #[Test]
    public function toolsThatTouchNoEntityDeclareNone(): void
    {
        foreach ([DescribeModelTool::class, GetServerTimeTool::class, WebSearchTool::class] as $className) {
            $this->assertTrue($this->tool($className)->authorizationRequirements(new Dictionary())?->isNone, $className);
        }
    }

    #[Test]
    public function theCollectorRewritesSubqueryVariablesOntoTheirCollection(): void
    {
        /** @var Predicate $predicate */
        $predicate = Predicate::format("customer.area.name == %@ AND SUBQUERY(items, \$x, \$x.product.price > 3).@count > 0", new ArrayClass(["North"]));
        $keyPaths = PredicateKeyPathCollector::keyPaths($predicate);
        foreach (["customer.area.name", "items", "items.product.price"] as $keyPath) {
            $this->assertTrue($keyPaths->containsElement($keyPath), $keyPath);
        }
        $this->assertFalse($keyPaths->contains(fn(string $keyPath): bool => str_contains($keyPath, "$") || str_contains($keyPath, "(")));
    }

    /**
     * @param class-string<AbstractTool> $className
     * @return list<string>
     */
    private function keys(string $className, Dictionary $arguments): array
    {
        $requirements = $this->tool($className)->authorizationRequirements($arguments);
        $this->assertNotNull($requirements);
        $this->assertFalse($requirements->isNone);
        return $requirements->requirements->map(fn(AuthorizationRequirement $requirement): string => $requirement->key)->array;
    }

    /**
     * @param class-string<AbstractTool> $className
     */
    private function tool(string $className): AbstractTool
    {
        $descriptor = new ReflectionClass(ModelDescriptor::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(ModelDescriptor::class, "schema")->setRawValue($descriptor, new ModelSchema($this->entities(), new PredicateGuideFactory()->make()));
        return new $className(new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor(), $descriptor);
    }

    /** @return Dictionary<EntitySchema> */
    private function entities(): Dictionary
    {
        return new Dictionary([
            "Order" => $this->entity("Order", ["total"], ["customer" => ["Customer", false], "lines" => ["Line", true], "items" => ["Item", true], "party" => ["Party", false]]),
            "Party" => $this->entity("Party", ["name"], [], true),
            "Customer" => $this->entity("Customer", ["name", "credit"], ["area" => ["Area", false], "orders" => ["Order", true]]),
            "Company" => $this->entity("Company", ["name"], []),
            "Area" => $this->entity("Area", ["name"], []),
            "Line" => $this->entity("Line", ["sku"], []),
            "Item" => $this->entity("Item", ["quantity"], ["product" => ["Product", false]]),
            "Product" => $this->entity("Product", ["price"], []),
        ]);
    }

    /**
     * @param list<string> $attributeNames
     * @param array<string, array{string, bool}> $relationshipTargets
     * @param bool $abstract
     */
    private function entity(string $name, array $attributeNames, array $relationshipTargets, bool $abstract = false): EntitySchema
    {
        /** @var Dictionary<AttributeSchema> $attributes */
        $attributes = new Dictionary();
        foreach ($attributeNames as $attributeName) {
            $attributes[$attributeName] = new AttributeSchema($attributeName, "string", true);
        }
        /** @var Dictionary<RelationshipSchema> $relationships */
        $relationships = new Dictionary();
        foreach ($relationshipTargets as $relationshipName => [$target, $toMany]) {
            $relationships[$relationshipName] = new RelationshipSchema($relationshipName, $target, $toMany, true);
        }
        return new EntitySchema($name, "App\\Model\\$name", $name, [], $attributes, $relationships, $abstract);
    }
}
