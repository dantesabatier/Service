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
        get => $this->optionalResourceNames ??= new ArrayClass(["favicon.ico", "favicon.png", "apple-touch-icon.png", "apple-touch-icon-precomposed.png", "browserconfig.xml", "robots.txt", "ads.txt", "manifest.json", "site.webmanifest"]);
    }
    /** @var list<string> */
    private const array resourceKeys = ["resourceURL", "privateFrameworksURL", "sharedFrameworksURL", "builtInPlugInsURL", "sharedSupportURL", "vendorURL", "nodeModulesURL"];
    /** @var Set<URL> */
    private Set $publicURLs {
        /**
         * @throws Exception
         */
        get {
            if (isset($this->publicURLs)) {
                return $this->publicURLs;
            }
            /** @var Set<Bundle> $bundles */
            $bundles = new Set([Bundle::main(), Bundle::bundleForClass(self::class)]);
            /** @var Set<string> $keys */
            $keys = new Set(self::resourceKeys);
            /** @var Set<URL> $publicURLs */
            $publicURLs = new Set([FileManager::default()->url(SearchPathDirectory::sharedPublicDirectory)]);
            $publicURLs->formUnion($bundles->flatMap(fn(Bundle $bundle): Set => $keys->compactMap(fn(string $key): ?URL => $bundle->valueForKey($key))));
            return $this->publicURLs = $publicURLs;
        }
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
            $isPublic = $this->publicURLs->contains(fn(URL $publicURL): bool => str_starts_with($resourceURL->path, $publicURL->path));
            return new StaticResourceDisposition(true, false, false, $isPublic, $isPublic);
        }
        return new StaticResourceDisposition(false, false, false, false, false);
    }
}
