<?php

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;

/** @internal */
final class DefaultStaticResourcePolicy implements StaticResourcePolicy
{
    /** @var ArrayClass<string> */
    private ArrayClass $optionalResourceNames {
        get => $this->optionalResourceNames ??= new ArrayClass(["favicon.ico"]);
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function evaluate(URL $resourceURL): StaticResourceDisposition
    {
        $resourceName = $resourceURL->lastPathComponent;
        if ($this->optionalResourceNames->containsElement($resourceName)) {
            return new StaticResourceDisposition(true, true, true, true, true);
        }
        $fileManager = FileManager::default();
        if ($fileManager->fileExists($resourceURL->path, $isDirectory) && !$isDirectory) {
            /** @var Set<Bundle> $bundles */
            $bundles = new Set([Bundle::main(), Bundle::bundleForClass(self::class)]);
            /** @var Set<URL> $publicURLs */
            $publicURLs = new Set([$fileManager->url(SearchPathDirectory::sharedPublicDirectory)]);
            $publicURLs->formUnion($bundles->compactMap(fn(Bundle $bundle): ?URL => $bundle->url($resourceName)));
            $isPublic = $publicURLs->contains(fn(URL $publicURL): bool => str_starts_with($resourceURL->path, $publicURL->path));
            return new StaticResourceDisposition(true, false, false, $isPublic, $isPublic);
        }
        return new StaticResourceDisposition(false, false, false, false, false);
    }
}
