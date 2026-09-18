<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Schema\AttributeSchema;
use Sabatier\Service\MCP\Schema\EntitySchema;
use Sabatier\Service\MCP\Schema\EnumSchema;
use Sabatier\Service\MCP\Schema\RelationshipSchema;
use Sabatier\Service\MCP\Schema\SchemaLocalizer;

final class SchemaLocalizerTest extends TestCase
{
    #[Test]
    public function anEntityWithoutVocabularyIsLabelledWithItsOwnName(): void
    {
        /** @var EntitySchema $order */
        $order = $this->localize([])["Order"];
        $this->assertSame("Order", $order->label);
        $this->assertSame([], $order->aliases);
    }

    #[Test]
    public function anEntityDescriptionBecomesItsLabel(): void
    {
        /** @var EntitySchema $order */
        $order = $this->localize(["entities" => ["Order" => ["description" => "Purchase order"]]])["Order"];
        $this->assertSame("Purchase order", $order->label);
    }

    #[Test]
    public function entityAliasesAreCarriedOnto(): void
    {
        /** @var EntitySchema $order */
        $order = $this->localize(["entities" => ["Order" => ["aliases" => ["pedido", "orden"]]]])["Order"];
        $this->assertSame(["pedido", "orden"], $order->aliases);
    }

    #[Test]
    public function anAttributeIsDescribedByItsQualifiedKey(): void
    {
        /** @var EntitySchema $order */
        $order = $this->localize(["attributes" => ["Order.total" => ["description" => "Amount due", "aliases" => ["importe"]]]])["Order"];
        $this->assertSame("Amount due", $order->attributes["total"]->label);
        $this->assertSame(["importe"], $order->attributes["total"]->aliases);
    }

    #[Test]
    public function anAttributeOfAnotherEntityIsNotApplied(): void
    {
        /** @var EntitySchema $order */
        $order = $this->localize(["attributes" => ["Invoice.total" => ["description" => "Wrong entity"]]])["Order"];
        $this->assertNull($order->attributes["total"]->label);
    }

    #[Test]
    public function anAttributeKeepsItsTypeNullabilityEnumAndTransience(): void
    {
        /** @var EntitySchema $order */
        $order = $this->localize(["attributes" => ["Order.status" => ["description" => "State"]]])["Order"];
        $status = $order->attributes["status"];
        $this->assertSame("enum", $status->type);
        $this->assertFalse($status->nullable);
        $this->assertTrue($status->transient);
        $this->assertNotNull($status->enum);
    }

    #[Test]
    public function relationshipsAreCarriedThroughUntouched(): void
    {
        /** @var EntitySchema $order */
        $order = $this->localize([])["Order"];
        $this->assertSame("Customer", $order->relationships["customer"]->target);
    }

    #[Test]
    public function theEntityNameAndBackingClassAreNeverRewritten(): void
    {
        /** @var EntitySchema $order */
        $order = $this->localize(["entities" => ["Order" => ["description" => "Purchase order"]]])["Order"];
        $this->assertSame("Order", $order->name);
        $this->assertSame("App\\Models\\Order", $order->className);
    }

    #[Test]
    public function anAbstractEntityStaysAbstract(): void
    {
        $this->assertTrue($this->localize([])["Document"]->abstract);
    }

    #[Test]
    public function everyEntityIsLocalizedNotJustTheFirst(): void
    {
        $entities = $this->localize(["entities" => ["Document" => ["description" => "Any document"]]]);
        $this->assertSame("Order", $entities["Order"]->label);
        $this->assertSame("Any document", $entities["Document"]->label);
    }

    /**
     * @param array<string, mixed> $vocabulary
     * @return Dictionary<EntitySchema>
     */
    private function localize(array $vocabulary): Dictionary
    {
        $status = new AttributeSchema("status", "enum", false, enum: new EnumSchema("Status", new Dictionary(["draft" => 1])), transient: true);
        $order = new EntitySchema("Order", "App\\Models\\Order", "Order", [], new Dictionary(["total" => new AttributeSchema("total", "float", true), "status" => $status]), new Dictionary(["customer" => new RelationshipSchema("customer", "Customer", false, true)]));
        $document = new EntitySchema("Document", "App\\Models\\Document", "Document", [], new Dictionary(), new Dictionary(), true);
        return new SchemaLocalizer()->apply(new Dictionary(["Order" => $order, "Document" => $document]), $vocabulary);
    }
}
