<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Schema;

use Sabatier\Foundation\Dictionary;

/**
 * Applies vocabulary entries to an extracted schema, adding descriptions and aliases.
 *
 * Merges the vocabulary loaded by `VocabularyRepository` into the raw
 * `Dictionary<EntitySchema>` produced by `ModelSchemaExtractor`, replacing
 * bare names with human-readable labels.
 */
final class SchemaLocalizer
{
    public function apply(Dictionary $entities, array $vocabulary): Dictionary
    {
        $entitiesVocabulary = $vocabulary["entities"] ?? [];
        $attributesVocabulary = $vocabulary["attributes"] ?? [];
        foreach ($entities as $name => $entity) {
            $entityVocabulary = $entitiesVocabulary[$name] ?? [];
            /** @var Dictionary<AttributeSchema> $localizedAttributes */
            $localizedAttributes = new Dictionary();
            foreach ($entity->attributes as $attrName => $attribute) {
                $key = "$name.$attrName";
                $data = $attributesVocabulary[$key] ?? [];
                $localizedAttributes[$attrName] = new AttributeSchema($attribute->name, $attribute->type, $attribute->nullable, $data["description"] ?? null, $data["aliases"] ?? [], $attribute->enum, $attribute->transient);
            }
            $entities[$name] = new EntitySchema($entity->name, $entity->className, $entityVocabulary["description"] ?? $name, $entityVocabulary["aliases"] ?? [], $localizedAttributes, $entity->relationships, $entity->abstract);
        }
        return $entities;
    }
}
