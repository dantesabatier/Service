<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLFileTypeMappings;

/** @internal */
class ResourceManager extends Responder
{
    public ArrayClass $optionalResourceNames {
        get => $this->optionalResourceNames ??= new ArrayClass(["favicon.ico"]);
    }
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::head, HTTPRequestMethod::get]);
    }
    public bool $isProtectedContentAvailable = true;
    public URL $resourceURL {
        get => $this->resourceURL ??= new URL($this->request->url->path, FileManager::default()->documentRootDirectory)->absoluteURL;
    }
    public bool $isFirstResponder {
        get {
            $resourceURL = $this->resourceURL;
            $path = $resourceURL->path;
            if (FileManager::default()->fileExists($path, $isDirectory) && !$isDirectory) {
                return true;
            }
            return $this->optionalResourceNames->containsElement($resourceURL->lastPathComponent);
        }
    }
    public Response $response {
        /**
         * @throws Exception
         */
        get {
            $body = null;
            $request = $this->request;
            $this->allowedMethods->containsElement($request->httpMethod) ?: throw new MethodNotAllowedException();
            $headers = new Dictionary();
            if ($request->httpMethod === HTTPRequestMethod::get || $request->httpMethod === HTTPRequestMethod::head) {
                $resourceURL = $this->resourceURL;
                $path = $resourceURL->path;
                $pathExtension = $resourceURL->pathExtension;
                $contentType = URLFileTypeMappings::shared()->mimeType($pathExtension);
                if (FileManager::default()->isReadableFile($path)) {
                    $content = FileManager::default()->contents($path) ?? throw new InternalServerErrorException();
                    if ($contentType && ($encoding = mb_detect_encoding($content))) {
                        $contentType .= "; charset=$encoding";
                    }
                    $headers["Content-Type"] = $contentType;
                    if ($request->httpMethod === HTTPRequestMethod::get) {
                        $body = $content;
                    }
                } else {
                    $headers["Content-Type"] = $contentType;
                }
                $headers["Cache-Control"] = "public, max-age=31536000, s-maxage=31536000, immutable";
            }
            return new CORSResponseDecorator(new ResponseHeaderSanitizerDecorator(new Response($request->url, HTTPStatusCode::ok, $headers, $body))->response, $request, $this->corsPolicy)->response;
        }
    }
}
