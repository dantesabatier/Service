<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

/** @internal */
final class DefaultStaticResourcePolicy implements StaticResourcePolicy
{
    /** @var ArrayClass<string> */
    private ArrayClass $optionalResourceNames {
        get => $this->optionalResourceNames ??= new ArrayClass(["favicon.ico"]);
    }

    #[Override]
    public function evaluate(URL $resourceURL): StaticResourceDisposition
    {
        if ($this->optionalResourceNames->containsElement($resourceURL->lastPathComponent)) {
            return new StaticResourceDisposition(true, true, true, true, true);
        }
        if (FileManager::default()->fileExists($resourceURL->path, $isDirectory) && !$isDirectory) {
            $publicURL = FileManager::default()->documentRootDirectory->appendingPathComponent("Public/");
            $isPublic = str_starts_with($resourceURL->path, $publicURL->path);
            return new StaticResourceDisposition(true, false, false, $isPublic, $isPublic);
        }
        return new StaticResourceDisposition(false, false, false, false, false);
    }
}
