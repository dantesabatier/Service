<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Schema;

use Sabatier\Foundation\Dictionary;

final class SchemaLocalizer
{
    public function apply(Dictionary $entities, array $vocabulary): Dictionary
    {
        foreach ($entities as $name => $entity) {
            $entityVocabulary = $vocabulary["entities"][$name] ?? [];
            /** @var Dictionary<AttributeSchema> $localizedAttributes */
            $localizedAttributes = new Dictionary();
            foreach ($entity->attributes as $attrName => $attribute) {
                $key = "$name.$attrName";
                $data = $vocabulary["attributes"][$key] ?? [];
                $localizedAttributes[$attrName] = new AttributeSchema($attribute->name, $attribute->type, $attribute->nullable, $data["es"] ?? null, $data["aliases"] ?? [], $attribute->enum);
            }
            $entities[$name] = new EntitySchema($entity->name, $entity->className, $entityVocabulary["es"] ?? $name, $entityVocabulary["aliases"] ?? [], $localizedAttributes, $entity->relationships);
        }
        return $entities;
    }
}
