<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Schema;

use BackedEnum;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;
use Sabatier\CoreData\AttributeDescription;
use Sabatier\CoreData\AttributeType;

/**
 * Creates `AttributeSchema` instances from CoreData attribute descriptions.
 *
 * Maps CoreData attribute types to JSON Schema primitives and resolves
 * backed enum types via reflection to produce `EnumSchema` entries.
 */
final class AttributeSchemaFactory
{
    public function make(string $className, string $name, AttributeDescription $attribute): AttributeSchema
    {
        if ($enum = $this->resolveEnum($className, $name)) {
            return new AttributeSchema($name, "enum", $attribute->isOptional, enum: $enum);
        }
        return new AttributeSchema($name, $this->mapType($attribute->type), $attribute->isOptional);
    }

    private function mapType(AttributeType $type): string
    {
        return match ($type) {
            AttributeType::integer16, AttributeType::integer32, AttributeType::integer64 => "integer",
            AttributeType::float, AttributeType::double, AttributeType::decimal => "float",
            AttributeType::boolean => "boolean",
            AttributeType::date => "date",
            AttributeType::string, AttributeType::uuid, AttributeType::uri => "string",
            default => "mixed",
        };
    }

    private function resolveEnum(string $className, string $attribute): ?EnumSchema
    {
        if (!class_exists($className)) {
            return null;
        }
        $reflection = new ReflectionClass($className);
        $method = "validate" . ucfirst($attribute);
        if (!$reflection->hasMethod($method)) {
            return null;
        }
        $parameter = $reflection->getMethod($method)->getParameters()[0] ?? null;
        $type = $parameter?->getType();
        $types = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];
        foreach ($types as $candidate) {
            if ($candidate instanceof ReflectionNamedType && enum_exists($candidate->getName())) {
                /** @var class-string<BackedEnum> $enum */
                $enum = $candidate->getName();
                return new EnumSchema($enum, array_reduce(
                    $enum::cases(),
                    function (array $carry, BackedEnum $case): array {
                        $carry[$case->name] = $case->value;
                        return $carry;
                    },
                    []
                ));
            }
        }
        return null;
    }
}
