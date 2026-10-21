<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Resources;

use ReflectionClass;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\DirectoryEnumerationOptions;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\string_is_equal;
use const Sabatier\Service\MCPResourcesDirectory;

/**
 * Resolves the MCP resources an application publishes.
 *
 * Discovery mirrors the tool mechanism: the application's `MCPResources` directory is scanned and each file's class
 * reconstructed as `App\MCPResources\{basename}`. The framework ships none of its own, so what this answers is
 * entirely what the application dropped in that directory.
 */
final readonly class ResourceResolver
{
    /** @return ArrayClass<AbstractResource> */
    public function resolve(): ArrayClass
    {
        $directoryURL = Bundle::main()->bundleURL->appendingPathComponent("src")->appendingPathComponent(MCPResourcesDirectory);
        if (!FileManager::default()->fileExists($directoryURL->path)) {
            return new ArrayClass();
        }
        return $this->fileURLs($directoryURL)->map(fn(URL $url): string => /** @var class-string<AbstractResource> */ "App\\" . MCPResourcesDirectory . "\\" . FileManager::default()->displayName($url->path))->filter($this->isValidResourceClass(...))->map(fn(string $class): AbstractResource => new $class());
    }

    /** @return ArrayClass<URL> */
    private function fileURLs(URL $directoryURL): ArrayClass
    {
        return FileManager::default()->contentsOfDirectory($directoryURL, null, DirectoryEnumerationOptions::skipsHiddenFiles)->filter(fn(URL $url): bool => string_is_equal($url->pathExtension, "php", CompareOptions::caseInsensitive));
    }

    private function isValidResourceClass(string $className): bool
    {
        if (!class_exists($className) || !is_subclass_of($className, AbstractResource::class)) {
            return false;
        }
        return new ReflectionClass($className)->isInstantiable();
    }
}
