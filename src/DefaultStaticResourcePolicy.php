<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

/** @internal */
class DefaultStaticResourcePolicy implements StaticResourcePolicy
{
    /** @var ArrayClass<string> */
    public ArrayClass $optionalResourceNames {
        get => $this->optionalResourceNames ??= new ArrayClass(["favicon.ico"]);
    }

    public function evaluate(URL $resourceURL): StaticResourceDisposition
    {
        $path = $resourceURL->path;
        if (FileManager::default()->fileExists($path, $isDirectory) && !$isDirectory) {
            return new StaticResourceDisposition(true, false, false, true);
        }
        if ($this->optionalResourceNames->containsElement($resourceURL->lastPathComponent)) {
            return new StaticResourceDisposition(true, true, true, true);
        }
        return new StaticResourceDisposition(false, false, false, false);
    }
}
