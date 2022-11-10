<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\HTTPRequestMethod;
use Sabatier\Foundation\HTTPStatusCode;
use Sabatier\Foundation\HTTPURLResponse;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLFileTypeMappings;

/** @internal */
class ResourceManager extends Responder
{
    public function __construct()
    {
        parent::__construct();
        $this->allowedMethods = new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::head, HTTPRequestMethod::options]);
    }

    public function isFirstResponder(): bool
    {
        return $this->request->url->pathExtension !== "";
    }

    public function response(): HTTPURLResponse
    {
        /** @var Dictionary<mixed> $headerFields */
        $headerFields = new Dictionary();
        switch ($this->request->httpMethod) {
            case HTTPRequestMethod::get:
            case HTTPRequestMethod::head:
                $fileManager = FileManager::default();
                $url = new URL($this->request->url->path, $fileManager->documentRootDirectory);
                $path = $url->path;
                if (!$fileManager->fileExists($path)) {
                    throw new NotFoundException("Not found, \"$url->lastPathComponent\"");
                }
                if (!$fileManager->isReadableFile($path)) {
                    throw new ForbiddenException();
                }
                if ($content = $fileManager->contents($path)) {
                    if (($contentType = URLFileTypeMappings::shared()->mimeType($url->pathExtension)) && ($encoding = mb_detect_encoding($content))) {
                        $contentType .= "; charset=$encoding";
                    }
                    $this->contentType = $contentType;
                    if ($this->request->httpMethod === HTTPRequestMethod::get) {
                        $this->content = $content;
                    }
                    $headerFields['Content-Type'] = $this->contentType;
                    $headerFields['Content-Disposition'] = "inline; filename=$url->lastPathComponent";
                }
                break;
            case HTTPRequestMethod::options:
                break;
            default:
                throw new MethodNotAllowedException();
        }
        return new HTTPURLResponse($this->request->url, HTTPStatusCode::ok, null, $headerFields);
    }
}
