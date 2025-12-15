<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLFileTypeMappings;

/** @internal */
class ResourceManager extends Responder
{
    public bool $isProtectedContentAvailable = true;
    public URL $resourceURL {
        get => $this->resourceURL ??= new URL($this->request->url->path, FileManager::default()->documentRootDirectory)->absoluteURL;
    }
    public bool $isFirstResponder {
        get => FileManager::default()->fileExists($this->resourceURL->path, $isDirectory) && !$isDirectory;
    }
    public Response $response {
        /**
         * @throws Exception
         */
        get {
            FileManager::default()->isReadableFile($this->resourceURL->path) ?: throw new MethodNotAllowedException();
            $body = null;
            $headerFields = new Dictionary();
            $request = $this->request;
            if ($request->httpMethod === HTTPRequestMethod::get || $request->httpMethod === HTTPRequestMethod::head) {
                $content = FileManager::default()->contents($this->resourceURL->path) ?? throw new InternalServerErrorException();
                if (($contentType = URLFileTypeMappings::shared()->mimeType($this->resourceURL->pathExtension)) && ($encoding = mb_detect_encoding($content))) {
                    $contentType .= "; charset=$encoding";
                }
                $headerFields["Content-Type"] = $contentType;
                if ($request->httpMethod === HTTPRequestMethod::get) {
                    $body = $content;
                }
                $headerFields["Cache-Control"] = "public, max-age=31536000, s-maxage=31536000, immutable";
            } elseif ($request->httpMethod !== HTTPRequestMethod::options) {
                throw new MethodNotAllowedException();
            }
            return new CORSResponseDecorator(new ResponseHeaderSanitizerDecorator(new Response($request->url, HTTPStatusCode::ok, $headerFields, $body))->response, $request)->response;
        }
    }
}
