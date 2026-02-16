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

/** @internal */
final class Uploader extends Responder
{
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::post]);
    }

    /**
     * @throws Exception
     */
    #[Action(decorators: [JSONDecorator::class])]
    public function upload(): void
    {
        $parsedBody = $this->request->parsedBody;
        $directory = $parsedBody["directory"] ?? throw new BadRequestException();
        preg_match("/^[A-Za-z0-9_-]+\$/", $directory) ?: throw new BadRequestException();
        $directoryURL = new URL($directory, FileManager::default()->documentRootDirectory)->absoluteURL;
        $keys = new Set([URLResourceKey::nameKey, URLResourceKey::pathKey]);
        $enumerator = new UploadsEnumerator($directoryURL, $keys);
        !$enumerator->isEmpty ?: throw new BadRequestException();
        /** @var ArrayClass<Dictionary<string>> $files */
        $files = new ArrayClass();
        foreach ($enumerator as $url) {
            $values = $url->resourceValues($keys);
            /** @var string $name */
            $name = $values->name;
            /** @var string $path */
            $path = $values->path;
            $files->append(new Dictionary([URLResourceKey::nameKey => $name, URLResourceKey::pathKey => $path]));
        }
        $this->data = $files;
    }
}
