<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use ReflectionClass;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\DirectoryEnumerationOptions;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\AggregateTool;
use Sabatier\Service\MCP\Tools\BatchDeleteTool;
use Sabatier\Service\MCP\Tools\BatchInsertTool;
use Sabatier\Service\MCP\Tools\BatchUpdateTool;
use Sabatier\Service\MCP\Tools\CountTool;
use Sabatier\Service\MCP\Tools\CreateTool;
use Sabatier\Service\MCP\Tools\DeleteTool;
use Sabatier\Service\MCP\Tools\DescribeModelTool;
use Sabatier\Service\MCP\Tools\FetchTool;
use Sabatier\Service\MCP\Tools\GroupByTool;
use Sabatier\Service\MCP\Tools\UpdateTool;
use function Sabatier\Foundation\string_is_equal;
use const Sabatier\Service\MCPToolsDirectory;

final readonly class ToolResolver
{
    /** @var list<class-string<AbstractTool>> */
    private const array builtInToolClasses = [
        DescribeModelTool::class,
        FetchTool::class,
        CountTool::class,
        AggregateTool::class,
        GroupByTool::class,
        CreateTool::class,
        UpdateTool::class,
        DeleteTool::class,
        BatchInsertTool::class,
        BatchUpdateTool::class,
        BatchDeleteTool::class,
    ];

    public function __construct(private ManagedObjectContext $context, private ModelDescriptor $descriptor)
    {
    }

    /** @return ArrayClass<AbstractTool> */
    public function resolve(): ArrayClass
    {
        /** @var ArrayClass<AbstractTool> $builtIn */
        $builtIn = new ArrayClass(self::builtInToolClasses)->map(fn(string $class): AbstractTool => new $class($this->context, $this->descriptor));
        $builtIn->appendContentsOf($this->discover());
        return $builtIn;
    }

    /** @return ArrayClass<AbstractTool> */
    private function discover(): ArrayClass
    {
        $directoryURL = Bundle::main()->bundleURL->appendingPathComponent("src")->appendingPathComponent(MCPToolsDirectory);
        if (!FileManager::default()->fileExists($directoryURL->path)) {
            return new ArrayClass([]);
        }
        return $this->fileURLs($directoryURL)->map(fn(URL $url): string => /** @var class-string<AbstractTool> */ "App\\" . MCPToolsDirectory . "\\" . FileManager::default()->displayName($url->path))->filter($this->isValidToolClass(...))->map(fn(string $class): AbstractTool => new $class($this->context, $this->descriptor));
    }

    /** @return ArrayClass<URL> */
    private function fileURLs(URL $directoryURL): ArrayClass
    {
        return FileManager::default()->contentsOfDirectory($directoryURL, null, DirectoryEnumerationOptions::skipsHiddenFiles)->filter(fn(URL $url): bool => string_is_equal($url->pathExtension, "php", CompareOptions::caseInsensitive));
    }

    private function isValidToolClass(string $className): bool
    {
        if (!class_exists($className) || !is_subclass_of($className, AbstractTool::class)) {
            return false;
        }
        return new ReflectionClass($className)->isInstantiable();
    }
}
