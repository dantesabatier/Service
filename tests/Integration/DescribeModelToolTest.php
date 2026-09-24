<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Schema\AttributeSchema;
use Sabatier\Service\MCP\Schema\EntitySchema;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Schema\ModelSchema;
use Sabatier\Service\MCP\Schema\PredicateGuide;
use Sabatier\Service\MCP\Schema\RelationshipSchema;
use Sabatier\Service\MCP\Tools\DescribeModelTool;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Sabatier\Foundation\ArrayClass;

/**
 * Exercises the two response modes of describe_model without a live store: a call with no
 * argument returns the lightweight index (counts, not expanded shapes), and a call naming one
 * or more entities returns their full attributes and relationships. An unknown name funnels
 * through the registry as a correctable failure carrying an actionable message for the model.
 */
final class DescribeModelToolTest extends TestCase
{
    private function makeEntity(string $name, int $attributeCount): EntitySchema
    {
        /** @var Dictionary<AttributeSchema> $attributes */
        $attributes = new Dictionary();
        for ($i = 0; $i < $attributeCount; $i++) {
            $attributes["field$i"] = new AttributeSchema("field$i", "string", true);
        }
        /** @var Dictionary<RelationshipSchema> $relationships */
        $relationships = new Dictionary(["customer" => new RelationshipSchema("customer", "Customer", false, true)]);
        return new EntitySchema($name, "App\\Model\\$name", "the $name", ["alias-$name"], $attributes, $relationships);
    }

    private function makeTool(): DescribeModelTool
    {
        /** @var Dictionary<EntitySchema> $entities */
        $entities = new Dictionary([
            "Order" => $this->makeEntity("Order", 40),
            "Small" => $this->makeEntity("Small", 2),
        ]);
        $guide = new PredicateGuide(["%K" => "key path"], ["="], ["example"]);
        $schema = new ModelSchema($entities, $guide);

        $descriptor = new ReflectionClass(ModelDescriptor::class)->newInstanceWithoutConstructor();
        new ReflectionClass(ModelDescriptor::class)->getProperty("schema")->setValue($descriptor, $schema);

        $context = new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
        return new DescribeModelTool($context, $descriptor);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(DescribeModelTool $tool, Dictionary $arguments): array
    {
        $content = $tool->execute($arguments);
        /** @var array<string, mixed> */
        return json_decode($content->first->text, true, flags: JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function noArgumentReturnsLightweightIndex(): void
    {
        $result = $this->decode($this->makeTool(), new Dictionary());
        $this->assertSame(["Order", "Small"], array_keys($result["entities"]));
        $order = $result["entities"]["Order"];
        $this->assertSame("App\\Model\\Order", $order["class"]);
        $this->assertSame(40, $order["attributes"]);
        $this->assertSame(1, $order["relationships"]);
        $this->assertArrayHasKey("predicate_syntax", $result);
        $this->assertArrayHasKey("usage", $result);
    }

    #[Test]
    public function indexDoesNotExpandAttributeShapes(): void
    {
        $result = $this->decode($this->makeTool(), new Dictionary());
        $this->assertIsInt($result["entities"]["Order"]["attributes"]);
    }

    #[Test]
    public function singleEntityStringReturnsFullDetail(): void
    {
        $result = $this->decode($this->makeTool(), new Dictionary(["entity" => "Order"]));
        $this->assertSame(["Order"], array_keys($result["entities"]));
        $this->assertCount(40, $result["entities"]["Order"]["attributes"]);
        $this->assertArrayHasKey("customer", $result["entities"]["Order"]["relationships"]);
        $this->assertArrayHasKey("predicate_syntax", $result);
    }

    #[Test]
    public function entityListReturnsDetailForEach(): void
    {
        $result = $this->decode($this->makeTool(), new Dictionary(["entity" => new ArrayClass(["Order", "Small"])]));
        $this->assertSame(["Order", "Small"], array_keys($result["entities"]));
        $this->assertCount(40, $result["entities"]["Order"]["attributes"]);
        $this->assertCount(2, $result["entities"]["Small"]["attributes"]);
    }

    #[Test]
    public function unknownEntityFailsWithActionableMessage(): void
    {
        $result = new ToolRegistry(new ArrayClass([$this->makeTool()]))->call("describe_model", new Dictionary(["entity" => "Nope"]));
        $this->assertTrue($result->isError);
        $this->assertStringContainsString("Unknown entity: \"Nope\"", $result->text);
        $this->assertStringContainsString("describe_model with no argument", $result->text);
    }
}
