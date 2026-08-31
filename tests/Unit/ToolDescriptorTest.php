<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use stdClass;

final class ToolDescriptorTest extends TestCase
{
    #[Test]
    public function omitsTitleWhenNull(): void
    {
        $descriptor = new ToolDescriptor("count", "Count entities.", ["type" => "object"]);
        $data = $descriptor->jsonSerialize();
        $this->assertArrayNotHasKey("title", $data);
        $this->assertNull($descriptor->annotations);
        $this->assertArrayNotHasKey("annotations", $data);
        $this->assertSame(["name", "description", "inputSchema"], array_keys($data));
    }

    #[Test]
    public function includesTitleWhenPresent(): void
    {
        $descriptor = new ToolDescriptor("persistent_history", "Query history.", ["type" => "object"], "Persistent History");
        $data = $descriptor->jsonSerialize();
        $this->assertArrayHasKey("title", $data);
        $this->assertSame("Persistent History", $data["title"]);
    }

    #[Test]
    public function serializesNameDescriptionAndInputSchema(): void
    {
        $schema = ["type" => "object", "required" => ["entity"]];
        $descriptor = new ToolDescriptor("count", "Count entities.", $schema);
        $data = $descriptor->jsonSerialize();
        $this->assertSame("count", $data["name"]);
        $this->assertSame("Count entities.", $data["description"]);
        $this->assertSame($schema, $data["inputSchema"]);
    }

    #[Test]
    public function preservesFalseAnnotationsAndAnIndependentLegacyTitle(): void
    {
        $annotations = ["title" => "Legacy title", "readOnlyHint" => true, "destructiveHint" => false, "idempotentHint" => false, "openWorldHint" => false];
        $descriptor = new ToolDescriptor("count", "Count entities.", ["type" => "object"], "Count", $annotations);

        $this->assertSame($annotations, $descriptor->jsonSerialize()["annotations"]);
        $this->assertSame("Count", $descriptor->jsonSerialize()["title"]);
    }

    #[Test]
    public function emptyAnnotationsSerializeAsAnObject(): void
    {
        $descriptor = new ToolDescriptor("count", "Count entities.", ["type" => "object"], annotations: []);

        $this->assertInstanceOf(stdClass::class, $descriptor->jsonSerialize()["annotations"]);
        $this->assertStringContainsString("\"annotations\":{}", (string)json_encode($descriptor));
    }
}
