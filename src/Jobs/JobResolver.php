<?php

declare(strict_types=1);

namespace Sabatier\Service\Jobs;

use ReflectionClass;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\DirectoryEnumerationOptions;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\string_is_equal;
use const Sabatier\Service\JobsDirectory;

/**
 * Resolves the complete set of jobs an application exposes to its CLI entry point.
 *
 * Auto-discovers job classes placed in the application's `Jobs` directory. Job
 * classes must extend `Job` and be instantiable. Returns an `ArrayClass<Job>`
 * ready to be registered with `JobRegistry`.
 */
final readonly class JobResolver
{
    /** @return ArrayClass<Job> */
    public function resolve(): ArrayClass
    {
        return $this->discover();
    }

    /** @return ArrayClass<Job> */
    private function discover(): ArrayClass
    {
        $directoryURL = Bundle::main()->bundleURL->appendingPathComponent("src")->appendingPathComponent(JobsDirectory);
        if (!FileManager::default()->fileExists($directoryURL->path)) {
            return new ArrayClass([]);
        }
        return $this->fileURLs($directoryURL)->map(fn(URL $url): string => /** @var class-string<Job> */ "App\\" . JobsDirectory . "\\" . FileManager::default()->displayName($url->path))->filter($this->isValidJobClass(...))->map(fn(string $class): Job => new $class());
    }

    /** @return ArrayClass<URL> */
    private function fileURLs(URL $directoryURL): ArrayClass
    {
        return FileManager::default()->contentsOfDirectory($directoryURL, null, DirectoryEnumerationOptions::skipsHiddenFiles)->filter(fn(URL $url): bool => string_is_equal($url->pathExtension, "php", CompareOptions::caseInsensitive));
    }

    private function isValidJobClass(string $className): bool
    {
        if (!class_exists($className) || !is_subclass_of($className, Job::class)) {
            return false;
        }
        return new ReflectionClass($className)->isInstantiable();
    }
}
