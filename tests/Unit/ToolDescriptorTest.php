<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Service\MCP\Response\ToolDescriptor;

final class ToolDescriptorTest extends TestCase
{
    #[Test]
    public function omitsTitleWhenNull(): void
    {
        $descriptor = new ToolDescriptor("count", "Count entities.", ["type" => "object"]);
        $data = $descriptor->jsonSerialize();
        $this->assertArrayNotHasKey("title", $data);
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
}
