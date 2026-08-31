<?php

// The framework's own tests inspect built-in tools without making them public API.
/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\AggregateTool;
use Sabatier\Service\MCP\Tools\CountTool;
use Sabatier\Service\MCP\Tools\CreateTool;
use Sabatier\Service\MCP\Tools\DeleteTool;
use Sabatier\Service\MCP\Tools\DescribeModelTool;
use Sabatier\Service\MCP\Tools\FetchTool;
use Sabatier\Service\MCP\Tools\GetServerTimeTool;
use Sabatier\Service\MCP\Tools\GroupByTool;
use Sabatier\Service\MCP\Tools\JobTool;
use Sabatier\Service\MCP\Tools\PersistentHistoryTool;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Sabatier\Service\MCP\Tools\UpdateTool;
use Sabatier\Service\MCP\Tools\WebSearchTool;

final class MCPToolAnnotationsTest extends TestCase
{
    /** @throws ReflectionException */
    #[Test]
    public function undeclaredEffectsUseConservativeProtocolDefaults(): void
    {
        $tool = new ReflectionClass(AnnotationProbeTool::class)->newInstanceWithoutConstructor();
        $descriptor = $this->descriptor(new ToolRegistry(new ArrayClass([$tool])));

        $this->assertSame(["readOnlyHint" => false, "destructiveHint" => true, "idempotentHint" => false, "openWorldHint" => true], $descriptor->annotations);
    }

    /** @throws ReflectionException */
    #[Test]
    public function customEffectsArePublishedWithoutEnablingCacheOrReadApproval(): void
    {
        $tool = new ReflectionClass(AdditiveAnnotationProbeTool::class)->newInstanceWithoutConstructor();
        $registry = new ToolRegistry(new ArrayClass([$tool]));

        $this->assertSame(["readOnlyHint" => false, "destructiveHint" => false, "idempotentHint" => true, "openWorldHint" => false], $this->descriptor($registry)->annotations);
        $this->assertFalse($registry->isReadOnlyCall($tool->name, new Dictionary()));
        $this->assertFalse($registry->isCacheable($tool->name));
    }

    /** @throws ReflectionException */
    #[Test]
    public function mixedHistoryToolIsNotAdvertisedAsReadOnlyEvenForFetch(): void
    {
        $tool = new ReflectionClass(PersistentHistoryTool::class)->newInstanceWithoutConstructor();
        $registry = new ToolRegistry(new ArrayClass([$tool]));

        $this->assertTrue($registry->isReadOnlyCall($tool->name, new Dictionary(["operation" => "fetch"])));
        $this->assertFalse($registry->isReadOnlyCall($tool->name, new Dictionary(["operation" => "purge"])));
        $annotations = $this->descriptor($registry)->annotations;
        $this->assertNotNull($annotations);
        $this->assertFalse($annotations["readOnlyHint"]);
        $this->assertTrue($annotations["destructiveHint"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function clockRemainsReadOnlyWithoutCachingItsAnswer(): void
    {
        $tool = new ReflectionClass(GetServerTimeTool::class)->newInstanceWithoutConstructor();
        $registry = new ToolRegistry(new ArrayClass([$tool]));

        $annotations = $this->descriptor($registry)->annotations;
        $this->assertNotNull($annotations);
        $this->assertTrue($annotations["readOnlyHint"]);
        $this->assertFalse($registry->isCacheable($tool->name));
    }

    /**
     * @param class-string<AbstractTool> $class The built-in tool being inspected without executing it.
     * @param bool $readOnly Whether all operations of this tool only read.
     * @param bool $openWorld Whether its domain includes external or unspecified entities.
     * @throws ReflectionException
     */
    #[Test]
    #[DataProvider("builtInTools")]
    public function builtInToolsDeclareTheirInteractionDomain(string $class, bool $readOnly, bool $openWorld): void
    {
        $tool = new ReflectionClass($class)->newInstanceWithoutConstructor();

        $this->assertSame($readOnly, $tool->isReadOnly);
        $this->assertSame($openWorld, $tool->isOpenWorld);
        if (!$readOnly) {
            $this->assertTrue($tool->isDestructive);
            $this->assertFalse($tool->isIdempotent);
        }
    }

    private function descriptor(ToolRegistry $registry): ToolDescriptor
    {
        $descriptor = $registry->list->first;
        $this->assertNotNull($descriptor);
        return $descriptor;
    }

    /** @return array<string, array{class-string<AbstractTool>, bool, bool}> */
    public static function builtInTools(): array
    {
        return [
            "describe_model" => [DescribeModelTool::class, true, false],
            "fetch" => [FetchTool::class, true, false],
            "count" => [CountTool::class, true, false],
            "aggregate" => [AggregateTool::class, true, false],
            "group_by" => [GroupByTool::class, true, false],
            "get_server_time" => [GetServerTimeTool::class, true, false],
            "web_search" => [WebSearchTool::class, true, true],
            "create" => [CreateTool::class, false, false],
            "update" => [UpdateTool::class, false, false],
            "delete" => [DeleteTool::class, false, false],
            "persistent_history" => [PersistentHistoryTool::class, false, false],
            "run_job" => [JobTool::class, false, true],
        ];
    }
}

class AnnotationProbeTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "annotation_probe";
    }
    #[Override]
    public array $inputSchema {
        get => ["type" => "object"];
    }

    /** @return ArrayClass<ContentItem> */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        return new ArrayClass();
    }
}

final class AdditiveAnnotationProbeTool extends AnnotationProbeTool
{
    #[Override]
    public bool $isDestructive {
        get => false;
    }
    #[Override]
    public bool $isIdempotent {
        get => true;
    }
    #[Override]
    public bool $isOpenWorld {
        get => false;
    }
}
