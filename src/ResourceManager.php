<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLFileTypeMappings;

class ResourceManager extends Responder
{
    public bool $isProtectedContentAvailable = true;
    public readonly URL $resourceURL;

    public function __construct()
    {
        parent::__construct();
        $this->allowedMethods = new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::head, HTTPRequestMethod::get]);
        $this->resourceURL = (new URL($this->request->url->path, FileManager::default()->documentRootDirectory))->absoluteURL;
    }

    #[Override]
    public function isFirstResponder(): bool
    {
        return FileManager::default()->fileExists($this->resourceURL->path, $isDirectory) && !$isDirectory;
    }

    #[Override]
    public function response(): HTTPURLResponse
    {
        FileManager::default()->isReadableFile($this->resourceURL->path) ?: throw new MethodNotAllowedException();
        switch ($this->request->httpMethod) {
            case HTTPRequestMethod::get:
            case HTTPRequestMethod::head:
                $content = FileManager::default()->contents($this->resourceURL->path) ?? throw new InternalServerErrorException();
                if (($contentType = URLFileTypeMappings::shared()->mimeType($this->resourceURL->pathExtension)) && ($encoding = mb_detect_encoding($content))) {
                    $contentType .= "; charset=$encoding";
                }
                $this->headerFields["Content-Type"] = $contentType;
                if ($this->request->httpMethod === HTTPRequestMethod::get) {
                    $this->content = $content;
                }
                break;
            case HTTPRequestMethod::options:
                break;
            default:
                throw new MethodNotAllowedException();
        }
        return new HTTPURLResponse($this->request->url, $this->statusCode, headerFields: $this->headerFields);
    }
}
