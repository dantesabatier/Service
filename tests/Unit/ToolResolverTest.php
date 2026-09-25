<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\ToolResolver;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\DeleteTool;
use Sabatier\Service\MCP\Tools\DescribeModelTool;
use Sabatier\Service\MCP\Tools\FetchTool;

/**
 * Fixes which tools an agent is handed. Discovery of an application's own `MCPTools` directory is
 * not exercised: this bundle has none, and creating one would be a change to the project layout.
 */
final class ToolResolverTest extends TestCase
{
    /** @throws ReflectionException */
    #[Test]
    public function everyBuiltInToolIsResolved(): void
    {
        $this->assertSame(12, $this->resolve()->count);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theCatalogueIsAdvertisedUnderTheProtocolNames(): void
    {
        $names = $this->resolve()->map(fn(AbstractTool $tool): string => $tool->name)->array;
        sort($names);
        $this->assertSame(["aggregate", "count", "create", "delete", "describe_model", "fetch", "get_server_time", "group_by", "persistent_history", "run_job", "update", "web_search"], $names);
    }

    /** @throws ReflectionException */
    #[Test]
    public function describeModelLeadsTheCatalogue(): void
    {
        // The model schema is what an agent has to read before it can name an entity in any other
        // call, so it is offered first.
        $this->assertInstanceOf(DescribeModelTool::class, $this->resolve()->first);
    }

    /** @throws ReflectionException */
    #[Test]
    public function everyResolvedToolCarriesTheContextAndDescriptorItWasGiven(): void
    {
        $context = new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
        $descriptor = new ReflectionClass(ModelDescriptor::class)->newInstanceWithoutConstructor();
        $tool = new ToolResolver($context, $descriptor)->resolve()->first;
        $this->assertSame($context, new ReflectionProperty(AbstractTool::class, "context")->getValue($tool));
        $this->assertSame($descriptor, new ReflectionProperty(AbstractTool::class, "descriptor")->getValue($tool));
    }

    /** @throws ReflectionException */
    #[Test]
    public function theReadAndWriteToolsAreBothOffered(): void
    {
        $classes = $this->resolve()->map(fn(AbstractTool $tool): string => $tool::class)->array;
        $this->assertContains(FetchTool::class, $classes);
        $this->assertContains(DeleteTool::class, $classes);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aBundleWithoutAToolsDirectoryContributesNothing(): void
    {
        $resolver = new ToolResolver(new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor(), new ReflectionClass(ModelDescriptor::class)->newInstanceWithoutConstructor());
        /** @var ArrayClass<AbstractTool> $discovered */
        $discovered = new ReflectionMethod(ToolResolver::class, "discover")->invoke($resolver);
        $this->assertTrue($discovered->isEmpty);
    }

    /** @throws ReflectionException */
    #[Test]
    public function acceptsAnInstantiableToolSubclass(): void
    {
        $this->assertTrue($this->isValidToolClass(FetchTool::class));
    }

    /** @throws ReflectionException */
    #[Test]
    public function rejectsAClassThatDoesNotExist(): void
    {
        $this->assertFalse($this->isValidToolClass("App\\MCPTools\\Nope"));
    }

    /** @throws ReflectionException */
    #[Test]
    public function rejectsAClassThatIsNotATool(): void
    {
        $this->assertFalse($this->isValidToolClass(Dictionary::class));
    }

    /** @throws ReflectionException */
    #[Test]
    public function rejectsTheAbstractBaseItself(): void
    {
        $this->assertFalse($this->isValidToolClass(AbstractTool::class));
    }

    /** @throws ReflectionException */
    private function isValidToolClass(string $className): bool
    {
        $resolver = new ToolResolver(new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor(), new ReflectionClass(ModelDescriptor::class)->newInstanceWithoutConstructor());
        /** @var bool */
        return new ReflectionMethod(ToolResolver::class, "isValidToolClass")->invoke($resolver, $className);
    }

    /**
     * @return ArrayClass<AbstractTool>
     * @throws ReflectionException
     */
    private function resolve(): ArrayClass
    {
        return new ToolResolver(new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor(), new ReflectionClass(ModelDescriptor::class)->newInstanceWithoutConstructor())->resolve();
    }
}
