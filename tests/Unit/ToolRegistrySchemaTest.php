<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Sabatier\Service\Testing\FixesRequestSecurityContext;

/**
 * Fixes what the registry answers when the model calls a tool with arguments its schema does not declare.
 *
 * A tool reads the keys it knows and ignores the rest, so `filter` where `predicate` was meant used to
 * produce a fetch with no filter at all — every row, returned as though it were the answer. The failure
 * is silent by construction, which is what makes it worth catching in the funnel both MCP and the
 * agent loop converge on.
 */
final class ToolRegistrySchemaTest extends TestCase
{
    use FixesRequestSecurityContext;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->fixRequestSecurityContext($this->unrestrictedRequestSecurityContext());
    }

    private function registry(): ToolRegistry
    {
        /** @var AbstractTool $tool */
        $tool = new ReflectionClass(SchemaProbeTool::class)->newInstanceWithoutConstructor();
        return new ToolRegistry(new ArrayClass([$tool]));
    }

    /** The call the schema declares runs, and nothing is in its way. */
    #[Test]
    public function aWellFormedCallRuns(): void
    {
        $result = $this->registry()->call("probe", new Dictionary(["entity" => "Order", "predicate" => "%K == %@"]));

        $this->assertFalse($result->isError);
        $this->assertSame("ran", $result->text);
    }

    /** Omitting an optional argument is not a complaint. */
    #[Test]
    public function anOmittedOptionalArgumentIsFine(): void
    {
        $this->assertFalse($this->registry()->call("probe", new Dictionary(["entity" => "Order"]))->isError);
    }

    /**
     * The case this exists for: a plausible key the schema does not declare.
     *
     * The answer names the key, lists what is accepted, and says to call again — everything the model
     * needs to correct itself. Executing instead would have run the tool without the filter the model
     * believed it had sent.
     */
    #[Test]
    public function anUndeclaredArgumentIsRefusedWithTheAcceptedOnes(): void
    {
        $result = $this->registry()->call("probe", new Dictionary(["entity" => "Order", "filter" => "%K == %@"]));

        $this->assertTrue($result->isError);
        $this->assertStringContainsString("filter", $result->text);
        $this->assertStringContainsString("predicate", $result->text, "The accepted arguments must be listed, or the model cannot find the right name.");
        $this->assertStringNotContainsString("ran", $result->text, "The tool must not have been executed.");
    }

    /** A required argument left out is named rather than left to the tool to discover. */
    #[Test]
    public function aMissingRequiredArgumentIsNamed(): void
    {
        $result = $this->registry()->call("probe", new Dictionary(["predicate" => "%K == %@"]));

        $this->assertTrue($result->isError);
        $this->assertStringContainsString("entity", $result->text);
    }

    /** A tool declaring no properties accepts anything: the check is for the model's mistakes, not a schema police. */
    #[Test]
    public function aToolWithoutDeclaredPropertiesAcceptsAnything(): void
    {
        /** @var AbstractTool $tool */
        $tool = new ReflectionClass(OpenProbeTool::class)->newInstanceWithoutConstructor();

        $this->assertFalse(new ToolRegistry(new ArrayClass([$tool]))->call("open_probe", new Dictionary(["whatever" => 1]))->isError);
    }
}

final class SchemaProbeTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "probe";
    }
    #[Override]
    public string $description {
        get => "Probe tool.";
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => [
                "entity" => ["type" => "string"],
                "predicate" => ["type" => "string"],
            ],
            "required" => ["entity"],
        ];
    }

    /** @return ArrayClass<ContentItem> */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        return new ArrayClass([new ContentItem("text", "ran")]);
    }
}

final class OpenProbeTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "open_probe";
    }
    #[Override]
    public string $description {
        get => "Open probe tool.";
    }
    #[Override]
    public array $inputSchema {
        get => ["type" => "object"];
    }

    /** @return ArrayClass<ContentItem> */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        return new ArrayClass([new ContentItem("text", "ran")]);
    }
}
