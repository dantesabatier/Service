<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\FetchTool;

final class CatalogProbeTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "catalog_probe";
    }
    #[Override]
    public array $inputSchema {
        get => ["type" => "object"];
    }

    /** @return Dictionary<AbstractTool> */
    public function catalog(): Dictionary
    {
        return $this->tools();
    }

    /** @return ArrayClass<ContentItem> */
    #[Override]
    protected function executeCore(Dictionary $arguments): ArrayClass
    {
        return new ArrayClass();
    }
}

final class AbstractToolCatalogTest extends TestCase
{
    /** @throws ReflectionException */
    #[Test]
    public function theCatalogKeysEveryResolvedToolByItsName(): void
    {
        $catalog = new CatalogProbeTool(new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor(), new ReflectionClass(ModelDescriptor::class)->newInstanceWithoutConstructor())->catalog();
        $this->assertSame(12, $catalog->count);
        $this->assertInstanceOf(FetchTool::class, $catalog["fetch"]);
    }
}
