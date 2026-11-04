<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Throwable;

final class ToolRegistryCloneTest extends TestCase
{
    /**
     * @throws ReflectionException
     * @throws Throwable
     */
    #[Test]
    public function aNestedCallDoesNotOverwriteTheArgumentsOfTheCallAroundIt(): void
    {
        $tool = $this->tool();
        $result = $tool->registry->call("nesting", new Dictionary(["depth" => 1]));
        $this->assertSame("1", $result->content->first?->text);
    }

    /**
     * @throws ReflectionException
     * @throws Throwable
     */
    #[Test]
    public function theRegisteredInstanceIsNeverTheOneThatRuns(): void
    {
        $tool = $this->tool();
        $tool->registry->call("nesting", new Dictionary(["depth" => 2]));
        $this->assertFalse(new ReflectionProperty(AbstractTool::class, "arguments")->isInitialized($tool));
        $this->assertNull($tool->structuredContent);
    }

    /** @throws ReflectionException */
    private function tool(): NestingTool
    {
        $tool = new ReflectionClass(NestingTool::class)->newInstanceWithoutConstructor();
        $tool->registry = new ToolRegistry(new ArrayClass([$tool]));
        return $tool;
    }
}

final class NestingTool extends AbstractTool
{
    #[Override]
    protected(set) string $name = "nesting";
    #[Override]
    protected(set) array $inputSchema = ["type" => "object", "properties" => ["depth" => ["type" => "integer"]]];
    public ToolRegistry $registry;

    /**
     * @return ArrayClass<ContentItem>
     * @throws Throwable
     */
    #[Override]
    protected function executeCore(): ArrayClass
    {
        if ($this->arguments["depth"] === 1) {
            $this->registry->call("nesting", new Dictionary(["depth" => 2]));
        }
        return $this->structuredResult($this->textResult((string)$this->arguments["depth"]), ["depth" => $this->arguments["depth"]]);
    }
}
