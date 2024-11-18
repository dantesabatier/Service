<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\SearchPathDomainMask;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLResourceKey;

class Uploader extends Responder
{
    public ArrayClass $allowedMethods {
        get => $this->allowedMethods ??= new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::post]);
    }
    public URL $url {
        get => $this->url ??= FileManager::default()->url(SearchPathDirectory::sharedPublicDirectory, SearchPathDomainMask::local, null, true);
    }

    /**
     * @throws Exception
     */
    #[Action]
    public function upload(): void
    {
        $keys = new Set([URLResourceKey::nameKey, URLResourceKey::pathKey]);
        $enumerator = new UploadsEnumerator($this->url, $keys);
        !$enumerator->isEmpty ?: throw new BadRequestException();
        /** @var ArrayClass<Dictionary<string>> $files */
        $files = new ArrayClass();
        foreach ($enumerator as $url) {
            $values = $url->resourceValues($keys);
            /** @var string $name */
            $name = $values->name;
            /** @var string $path */
            $path = $values->path;
            $files[] = new Dictionary([URLResourceKey::nameKey => $name, URLResourceKey::pathKey => $path]);
        }
        $this->content = json_encode($files);
        $this->headerFields["Content-Type"] = "application/json";
    }
}
