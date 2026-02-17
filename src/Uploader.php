<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
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
        $keys = new Set([URLResourceKey::nameKey]);
        $enumerator = new UploadsEnumerator($directoryURL, $keys);
        !$enumerator->isEmpty ?: throw new BadRequestException();
        /** @var ArrayClass<Dictionary<string>> $files */
        $files = new ArrayClass();
        foreach ($enumerator as $url) {
            $values = $url->resourceValues($keys);
            /** @var string $name */
            $name = $values->name;
            $files->append(new Dictionary([URLResourceKey::nameKey => $name]));
        }
        $this->data = $files;
    }
}
