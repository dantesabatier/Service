<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLFileTypeMappings;

/** @internal */
class ResourceManager extends Responder
{
    public bool $isProtectedContentAvailable = true;
    private readonly URL $resourceURL;

    public function __construct()
    {
        parent::__construct();
        unset($this->resourceURL);
    }

    public function __get(string $name)
    {
        if ($name == "resourceURL") {
            $this->$name = (new URL($this->request->url->path, FileManager::default()->documentRootDirectory))->absoluteURL;
            return $this->$name;
        } else {
            return parent::__get($name);
        }
    }

    #[Override]
    public function isFirstResponder(): bool
    {
        return match ($this->request->httpMethod) {
            HTTPRequestMethod::get, HTTPRequestMethod::head => FileManager::default()->fileExists($this->resourceURL->path, $isDirectory) && !$isDirectory && FileManager::default()->isReadableFile($this->resourceURL->path),
            default => throw new MethodNotAllowedException()
        };
    }

    #[Override]
    public function response(): HTTPURLResponse
    {
        switch ($this->request->httpMethod) {
            case HTTPRequestMethod::get:
            case HTTPRequestMethod::head:
                if (!($content = FileManager::default()->contents($this->resourceURL->path))) {
                    throw new NotFoundException();
                }
                if (($contentType = URLFileTypeMappings::shared()->mimeType($this->resourceURL->pathExtension)) && ($encoding = mb_detect_encoding($content))) {
                    $contentType .= "; charset=$encoding";
                }
                $this->contentType = $contentType;
                if ($this->request->httpMethod === HTTPRequestMethod::get) {
                    $this->content = $content;
                }
                break;
            case HTTPRequestMethod::options:
                break;
            default:
                throw new MethodNotAllowedException();
        }
        return new HTTPURLResponse($this->request->url);
    }
}
