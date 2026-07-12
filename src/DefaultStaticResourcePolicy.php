<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\string_split_trimmed;

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
        get => $this->publicURLs ??= new Set([FileManager::default()->url(SearchPathDirectory::sharedPublicDirectory)])->union(new Set([Bundle::main(), Bundle::bundleForClass(self::class)])->flatMap(fn(Bundle $bundle): Set => $this->resourceKeys->compactMap(fn(string $key): ?URL => $bundle->valueForKey($key))))->union($this->configuredPublicURLs);
    }
    /** @var Set<URL> Extra public directories declared through the STATIC_PUBLIC_DIRECTORIES environment variable, resolved relative to the bundle root and kept only when they exist on disk. */
    private Set $configuredPublicURLs {
        get {
            if (isset($this->configuredPublicURLs)) {
                return $this->configuredPublicURLs;
            }
            $configured = (string)ProcessInfo::processInfo()->environment[StaticPublicDirectoriesKey];
            $bundleURL = Bundle::main()->bundleURL;
            return $this->configuredPublicURLs = new Set(new ArrayClass(string_split_trimmed($configured))->compactMap(function (string $name) use ($bundleURL): ?URL {
                if ($name === "") {
                    return null;
                }
                $url = $bundleURL->appendingPathComponent($name);
                return FileManager::default()->fileExists($url->path, $isDirectory) && $isDirectory ? $url : null;
            }));
        }
    }
    /** @var int The `max-age` applied to public bundle resources, overridable through the STATIC_RESOURCE_MAX_AGE environment variable. */
    private int $bundleMaxAge {
        get => $this->bundleMaxAge ??= (int)(ProcessInfo::processInfo()->environment[StaticResourceMaxAgeKey] ?? StaticResourceMaxAgeDefault);
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function evaluate(URL $resourceURL): StaticResourceDisposition
    {
        $resourceName = $resourceURL->lastPathComponent;
        if ($this->optionalResourceNames->containsElement($resourceName)) {
            return new StaticResourceDisposition(true, true, true, true, true, StaticResourceOptionalMaxAgeDefault);
        }
        if (FileManager::default()->fileExists($resourceURL->path, $isDirectory) && !$isDirectory) {
            $isPublic = $this->publicURLs->contains(fn(URL $publicURL): bool => str_starts_with($resourceURL->path, $publicURL->path));
            return new StaticResourceDisposition(true, false, false, $isPublic, $isPublic, $isPublic ? $this->bundleMaxAge : 0, $isPublic);
        }
        return new StaticResourceDisposition(false, false, false, false, false);
    }
}
