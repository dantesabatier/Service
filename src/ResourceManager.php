<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLFileTypeMappings;

class ResourceManager extends Responder
{
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::head, HTTPRequestMethod::get]);
    }
    public readonly URL $resourceURL;

    public function __construct()
    {
        parent::__construct();
        $this->resourceURL = new URL($this->request->url->path, FileManager::default()->documentRootDirectory)->absoluteURL;
    }

    public bool $isFirstResponder {
        get => FileManager::default()->fileExists($this->resourceURL->path, $isDirectory) && !$isDirectory;
    }

    public Response $response {
        get {
            FileManager::default()->isReadableFile($this->resourceURL->path) ?: throw new MethodNotAllowedException();
            $request = $this->request;
            switch ($request->httpMethod) {
                case HTTPRequestMethod::get:
                case HTTPRequestMethod::head:
                    $content = FileManager::default()->contents($this->resourceURL->path) ?? throw new InternalServerErrorException();
                    if (($contentType = URLFileTypeMappings::shared()->mimeType($this->resourceURL->pathExtension)) && ($encoding = mb_detect_encoding($content))) {
                        $contentType .= "; charset=$encoding";
                    }
                    $this->headerFields["Content-Type"] = $contentType;
                    if ($request->httpMethod === HTTPRequestMethod::get) {
                        $this->content = $content;
                    }
                    $this->headerFields["Cache-Control"] = "public, max-age=31536000, s-maxage=31536000, immutable";
                    break;
                case HTTPRequestMethod::options:
                    break;
                default:
                    throw new MethodNotAllowedException();
            }
            return new Response($this);
        }
    }
}
