<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Sabatier\CoreData\AttributeDescription;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\CoreData\PersistentStoreCoordinator;
use Sabatier\CoreData\RelationshipDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Service\MCP\Schema\AttributeSchemaFactory;
use Sabatier\Service\MCP\Schema\EntitySchema;
use Sabatier\Service\MCP\Schema\ModelSchemaExtractor;
use const Sabatier\CoreData\ManagedObjectEntityNameKey;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

final class ModelSchemaExtractorTest extends TestCase
{
    /** @throws ReflectionException */
    #[Test]
    public function aContextWithoutAModelIsAFatalMisconfiguration(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        new ModelSchemaExtractor(new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor(), new AttributeSchemaFactory())->extract();
    }

    /** @throws ReflectionException */
    #[Test]
    public function anEmptyModelYieldsNoEntities(): void
    {
        $this->assertTrue($this->extract(new ArrayClass())->isEmpty);
    }

    /** @throws ReflectionException */
    #[Test]
    public function everyEntityIsExposedUnderItsOwnName(): void
    {
        $entities = $this->extract(new ArrayClass([$this->entity("Order"), $this->entity("Customer")]));
        $this->assertSame(["Order", "Customer"], $entities->keys->array);
    }

    /** @throws ReflectionException */
    #[Test]
    public function everyEntityCarriesTheIdentityAttributesTheProtocolNeeds(): void
    {
        /** @var EntitySchema $order */
        $order = $this->extract(new ArrayClass([$this->entity("Order")]))["Order"];
        $this->assertSame("int64", $order->attributes[ManagedObjectObjectIDKey]->type);
        $this->assertFalse($order->attributes[ManagedObjectObjectIDKey]->nullable);
        $this->assertSame("string", $order->attributes[ManagedObjectEntityNameKey]->type);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theModelsOwnAttributesAreAddedAlongsideTheIdentityOnes(): void
    {
        /** @var EntitySchema $order */
        $order = $this->extract(new ArrayClass([$this->entity("Order", attributes: ["total" => AttributeType::decimal])]))["Order"];
        $this->assertSame("float", $order->attributes["total"]->type);
        $this->assertSame([ManagedObjectObjectIDKey, ManagedObjectEntityNameKey, "total"], $order->attributes->keys->array);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anEntityWithoutRelationshipsExposesNone(): void
    {
        /** @var EntitySchema $order */
        $order = $this->extract(new ArrayClass([$this->entity("Order")]))["Order"];
        $this->assertTrue($order->relationships->isEmpty);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aRelationshipCarriesItsTargetCardinalityAndOptionality(): void
    {
        $customer = $this->entity("Customer");
        $order = $this->entity("Order", relationships: ["customer" => [$customer, false, true], "items" => [$customer, true, false]]);
        /** @var EntitySchema $schema */
        $schema = $this->extract(new ArrayClass([$order, $customer]))["Order"];
        $this->assertSame("Customer", $schema->relationships["customer"]->target);
        $this->assertFalse($schema->relationships["customer"]->toMany);
        $this->assertTrue($schema->relationships["customer"]->nullable);
        $this->assertTrue($schema->relationships["items"]->toMany);
        $this->assertFalse($schema->relationships["items"]->nullable);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theBackingClassIsCarriedOntoTheSchema(): void
    {
        /** @var EntitySchema $order */
        $order = $this->extract(new ArrayClass([$this->entity("Order", className: "App\\Models\\Order")]))["Order"];
        $this->assertSame("App\\Models\\Order", $order->className);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anEntityWithoutABackingClassFallsBackToItsName(): void
    {
        /** @var EntitySchema $order */
        $order = $this->extract(new ArrayClass([$this->entity("Order")]))["Order"];
        $this->assertSame("Order", $order->className);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anAbstractEntityIsMarkedAsSuch(): void
    {
        $entities = $this->extract(new ArrayClass([$this->entity("Document", isAbstract: true), $this->entity("Invoice")]));
        $this->assertTrue($entities["Document"]->abstract);
        $this->assertFalse($entities["Invoice"]->abstract);
    }

    /**
     * @param array<string, AttributeType> $attributes
     * @param array<string, array{EntityDescription, bool, bool}> $relationships
     */
    private function entity(string $name, string $className = "", array $attributes = [], array $relationships = [], bool $isAbstract = false): EntityDescription
    {
        $entity = new EntityDescription();
        $entity->name = $name;
        $entity->isAbstract = $isAbstract;
        if ($className !== "") {
            $entity->managedObjectClassName = $className;
        }
        /** @var ArrayClass<mixed> $properties */
        $properties = new ArrayClass();
        new Dictionary($attributes)->forEach(function (AttributeType $type, string $attributeName) use ($properties): void {
            $attribute = new AttributeDescription();
            $attribute->name = $attributeName;
            $attribute->type = $type;
            $properties->append($attribute);
        });
        foreach ($relationships as $relationshipName => [$destination, $toMany, $optional]) {
            $relationship = new RelationshipDescription();
            $relationship->name = $relationshipName;
            new ReflectionProperty(RelationshipDescription::class, "destinationEntity")->setRawValue($relationship, $destination);
            $relationship->isToMany = $toMany;
            $relationship->isOptional = $optional;
            $properties->append($relationship);
        }
        $entity->properties = $properties;
        return $entity;
    }

    /**
     * @param ArrayClass<EntityDescription> $entities
     * @return Dictionary<EntitySchema>
     * @throws ReflectionException
     */
    private function extract(ArrayClass $entities): Dictionary
    {
        $model = new ManagedObjectModel();
        $model->entities = $entities;
        $context = new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
        $context->persistentStoreCoordinator = new PersistentStoreCoordinator($model);
        return new ModelSchemaExtractor($context, new AttributeSchemaFactory())->extract();
    }
}
