<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLFileTypeMappings;

class ResourceManager extends Responder
{
    public readonly URL $url;

    public function __construct()
    {
        parent::__construct();
        $this->allowedMethods = new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::head, HTTPRequestMethod::options]);
        $this->url = new URL($this->request->url->path, FileManager::default()->documentRootDirectory);
    }

    public function isFirstResponder(): bool
    {
        return FileManager::default()->fileExists($this->url->path, $isDirectory) && !$isDirectory && FileManager::default()->isReadableFile($this->url->path);
    }

    public function response(): HTTPURLResponse
    {
        switch ($this->request->httpMethod) {
            case HTTPRequestMethod::get:
            case HTTPRequestMethod::head:
                if ($content = FileManager::default()->contents($this->url->path)) {
                    if (($contentType = URLFileTypeMappings::shared()->mimeType($this->url->pathExtension)) && ($encoding = mb_detect_encoding($content))) {
                        $contentType .= "; charset=$encoding";
                    }
                    $this->contentType = $contentType;
                    if ($this->request->httpMethod === HTTPRequestMethod::get) {
                        $this->content = $content;
                    }
                    $this->contentDisposition = "inline; filename={$this->url->lastPathComponent}";
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
