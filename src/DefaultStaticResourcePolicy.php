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
    /** @var Set<string> */
    private Set $resourceKeys {
        get => $this->resourceKeys ??= new Set(self::resourceKeys);
    }
    /** @var Set<URL> */
    private Set $publicURLs {
        /**
         * @throws Exception
         */
        get => $this->publicURLs ??= new Set([FileManager::default()->url(SearchPathDirectory::sharedPublicDirectory)])->union(new Set([Bundle::main(), Bundle::bundleForClass(self::class)])->flatMap(fn(Bundle $bundle): Set => $this->resourceKeys->compactMap(fn(string $key): ?URL => $bundle->valueForKey($key))));
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
        if (FileManager::default()->fileExists($resourceURL->path, $isDirectory) && !$isDirectory) {
            $isPublic = $this->publicURLs->contains(fn(URL $publicURL): bool => str_starts_with($resourceURL->path, $publicURL->path));
            return new StaticResourceDisposition(true, false, false, $isPublic, $isPublic);
        }
        return new StaticResourceDisposition(false, false, false, false, false);
    }
}
