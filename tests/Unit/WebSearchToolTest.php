<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Sabatier\Service\MCP\Tools\WebSearchTool;
use Throwable;

/**
 * Exercises the correctable failure paths of web_search through the framework's built-in tool
 * without a live store or network: a blank query is a malformed call, and a missing
 * WEB_SEARCH_API_KEY is a configuration state the model must be told about. Both funnel
 * through the registry as failed ToolResults.
 */
final class WebSearchToolTest extends TestCase
{
    /** @throws ReflectionException */
    private function makeRegistry(): ToolRegistry
    {
        $context = new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
        $descriptor = new ReflectionClass(ModelDescriptor::class)->newInstanceWithoutConstructor();
        return new ToolRegistry(new ArrayClass([new WebSearchTool($context, $descriptor)]));
    }

    /** @throws Throwable */
    #[Test]
    public function blankQueryFunnelsAsCorrectableFailure(): void
    {
        $result = $this->makeRegistry()->call("web_search", new Dictionary(["query" => "   "]));
        $this->assertTrue($result->isError);
        $this->assertStringContainsString("non-empty query", $result->text);
    }

    /** @throws Throwable */
    #[Test]
    public function missingApiKeyFunnelsAsConfigurationFailure(): void
    {
        $result = $this->makeRegistry()->call("web_search", new Dictionary(["query" => "cotton fabric price"]));
        $this->assertTrue($result->isError);
        $this->assertStringContainsString("WEB_SEARCH_API_KEY", $result->text);
    }
}
