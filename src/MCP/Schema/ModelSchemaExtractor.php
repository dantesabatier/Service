<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Schema;

use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\CoreData\ManagedObjectEntityNameKey;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

/** @internal */
final readonly class ModelSchemaExtractor
{
    public function __construct(private ManagedObjectContext $context, private AttributeSchemaFactory $attributes)
    {
    }

    public function extract(): Dictionary
    {
        /** @var ManagedObjectModel $model */
        $model = $this->context->persistentStoreCoordinator?->managedObjectModel ?? fatal_error('ManagedObjectContext does not have a persistent store coordinator or model');
        /** @var Dictionary<EntitySchema> $entities */
        $entities = new Dictionary();
        foreach ($model->entitiesByName as $name => $entity) {
            /** @var Dictionary<AttributeSchema> $attributes */
            $attributes = new Dictionary([ManagedObjectObjectIDKey => new AttributeSchema(ManagedObjectObjectIDKey, "int64", false, "The object's unique identifier"), ManagedObjectEntityNameKey => new AttributeSchema(ManagedObjectEntityNameKey, "string", false, "The name of the entity this object belongs to")]);
            foreach ($entity->attributesByName as $attrName => $attribute) {
                $attributes[$attrName] = $this->attributes->make($entity->managedObjectClassName ?? $name, $attrName, $attribute);
            }
            /** @var Dictionary<RelationshipSchema> $relationships */
            $relationships = new Dictionary();
            foreach ($entity->relationshipsByName as $relName => $relationship) {
                $relationships[$relName] = new RelationshipSchema($relName, $relationship->destinationEntity->name, $relationship->isToMany, $relationship->isOptional);
            }
            $entities[$name] = new EntitySchema($name, $entity->managedObjectClassName ?? $name, $name, [], $attributes, $relationships);
        }
        return $entities;
    }
}
