<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\CoreData\AttributeDescription;
use Sabatier\CoreData\AttributeType;
use Sabatier\Service\MCP\Schema\AttributeSchemaFactory;

enum SchemaFactoryStatusFixture: int
{
    case draft = 1;
    case active = 2;
}

enum SchemaFactoryTierFixture: string
{
    case free = "free";
    case paid = "paid";
}

final class SchemaFactorySubjectFixture
{
    public function validateStatus(SchemaFactoryStatusFixture $status): void
    {
    }

    public function validateTier(?SchemaFactoryTierFixture $tier): void
    {
    }

    public function validateName(string $name): void
    {
    }

    public function validateGrade(int|SchemaFactoryTierFixture $grade): void
    {
    }

    public function validateWeight(int|string $weight): void
    {
    }

    public function validateNothing(): void
    {
    }
}

final class AttributeSchemaFactoryTest extends TestCase
{
    /** @return iterable<string, array{AttributeType, string}> */
    public static function attributeTypeProvider(): iterable
    {
        yield "integer16" => [AttributeType::integer16, "integer"];
        yield "integer32" => [AttributeType::integer32, "integer"];
        yield "integer64" => [AttributeType::integer64, "integer"];
        yield "float" => [AttributeType::float, "float"];
        yield "double" => [AttributeType::double, "float"];
        yield "decimal" => [AttributeType::decimal, "float"];
        yield "boolean" => [AttributeType::boolean, "boolean"];
        yield "date" => [AttributeType::date, "date"];
        yield "string" => [AttributeType::string, "string"];
        yield "uuid" => [AttributeType::uuid, "string"];
        yield "uri" => [AttributeType::uri, "string"];
        yield "binaryData falls through to mixed" => [AttributeType::binaryData, "mixed"];
        yield "undefined falls through to mixed" => [AttributeType::undefined, "mixed"];
    }

    #[Test]
    #[DataProvider("attributeTypeProvider")]
    public function mapsEachAttributeTypeOntoItsSchemaType(AttributeType $type, string $expected): void
    {
        $schema = new AttributeSchemaFactory()->make(SchemaFactorySubjectFixture::class, "amount", $this->attribute($type));
        $this->assertSame($expected, $schema->type);
        $this->assertNull($schema->enum);
    }

    #[Test]
    public function carriesTheNameOptionalityAndTransienceThrough(): void
    {
        $attribute = $this->attribute(AttributeType::string, isOptional: false, isTransient: true);
        $schema = new AttributeSchemaFactory()->make(SchemaFactorySubjectFixture::class, "label", $attribute);
        $this->assertSame("label", $schema->name);
        $this->assertFalse($schema->nullable);
        $this->assertTrue($schema->transient);
    }

    #[Test]
    public function resolvesAnIntegerBackedEnumFromItsValidateMethod(): void
    {
        $schema = new AttributeSchemaFactory()->make(SchemaFactorySubjectFixture::class, "status", $this->attribute(AttributeType::integer16));
        $this->assertSame("enum", $schema->type);
        $this->assertSame(SchemaFactoryStatusFixture::class, $schema->enum?->className);
        $this->assertSame(["draft" => 1, "active" => 2], $schema->enum->cases->array);
    }

    #[Test]
    public function resolvesAnEnumFromANullableValidateParameter(): void
    {
        $schema = new AttributeSchemaFactory()->make(SchemaFactorySubjectFixture::class, "tier", $this->attribute(AttributeType::string));
        $this->assertSame("enum", $schema->type);
        $this->assertSame(SchemaFactoryTierFixture::class, $schema->enum?->className);
        $this->assertSame(["free" => "free", "paid" => "paid"], $schema->enum->cases->array);
    }

    #[Test]
    public function findsTheEnumAmongTheMembersOfAUnionTypedValidateParameter(): void
    {
        $schema = new AttributeSchemaFactory()->make(SchemaFactorySubjectFixture::class, "grade", $this->attribute(AttributeType::integer16));
        $this->assertSame("enum", $schema->type);
        $this->assertSame(SchemaFactoryTierFixture::class, $schema->enum?->className);
    }

    #[Test]
    public function fallsBackWhenNoMemberOfAUnionTypedValidateParameterIsAnEnum(): void
    {
        $schema = new AttributeSchemaFactory()->make(SchemaFactorySubjectFixture::class, "weight", $this->attribute(AttributeType::integer16));
        $this->assertSame("integer", $schema->type);
        $this->assertNull($schema->enum);
    }

    #[Test]
    public function anEnumAttributeStillCarriesItsOptionalityAndTransience(): void
    {
        $attribute = $this->attribute(AttributeType::integer16, isOptional: false, isTransient: true);
        $schema = new AttributeSchemaFactory()->make(SchemaFactorySubjectFixture::class, "status", $attribute);
        $this->assertFalse($schema->nullable);
        $this->assertTrue($schema->transient);
    }

    #[Test]
    public function fallsBackToTheMappedTypeWhenTheValidateParameterIsNotAnEnum(): void
    {
        $schema = new AttributeSchemaFactory()->make(SchemaFactorySubjectFixture::class, "name", $this->attribute(AttributeType::string));
        $this->assertSame("string", $schema->type);
        $this->assertNull($schema->enum);
    }

    #[Test]
    public function fallsBackWhenTheValidateMethodTakesNoParameter(): void
    {
        $schema = new AttributeSchemaFactory()->make(SchemaFactorySubjectFixture::class, "nothing", $this->attribute(AttributeType::string));
        $this->assertSame("string", $schema->type);
        $this->assertNull($schema->enum);
    }

    #[Test]
    public function fallsBackWhenTheClassDeclaresNoValidateMethodForTheAttribute(): void
    {
        $schema = new AttributeSchemaFactory()->make(SchemaFactorySubjectFixture::class, "absent", $this->attribute(AttributeType::string));
        $this->assertSame("string", $schema->type);
        $this->assertNull($schema->enum);
    }

    #[Test]
    public function fallsBackWhenTheClassDoesNotExist(): void
    {
        $schema = new AttributeSchemaFactory()->make("App\\Models\\Nope", "status", $this->attribute(AttributeType::integer16));
        $this->assertSame("integer", $schema->type);
        $this->assertNull($schema->enum);
    }

    private function attribute(AttributeType $type, bool $isOptional = true, bool $isTransient = false): AttributeDescription
    {
        $attribute = new AttributeDescription();
        $attribute->type = $type;
        $attribute->isOptional = $isOptional;
        $attribute->isTransient = $isTransient;
        return $attribute;
    }
}
