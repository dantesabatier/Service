<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Schema;

use Sabatier\Foundation\Dictionary;

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
                $localizedAttributes[$attrName] = new AttributeSchema($attribute->name, $attribute->type, $attribute->nullable, $data["description"] ?? null, $data["aliases"] ?? [], $attribute->enum);
            }
            $entities[$name] = new EntitySchema($entity->name, $entity->className, $entityVocabulary["description"] ?? $name, $entityVocabulary["aliases"] ?? [], $localizedAttributes, $entity->relationships);
        }
        return $entities;
    }
}
