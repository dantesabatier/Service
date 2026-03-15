<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLFileTypeMappings;

/** @internal */
final class ResourceManager extends Responder
{
    /** @var ArrayClass<string> */
    #[Override]
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::head, HTTPRequestMethod::get]);
    }
    /** @var ArrayClass<string> */
    #[Override]
    public ArrayClass $allowedHeaders {
        get => new ArrayClass(["Content-Type"]);
    }
    private(set) URL $resourceURL {
        get => $this->resourceURL ??= new URL($this->request->url->path, FileManager::default()->documentRootDirectory)->absoluteURL;
    }
    private StaticResourceDisposition $staticResourceDisposition {
        get => $this->staticResourceDisposition ??= Application::shared()->staticResourcePolicy->evaluate($this->resourceURL);
    }
    #[Override]
    public bool $isFirstResponder {
        get => $this->staticResourceDisposition->shouldHandle;
    }
    #[Override]
    public bool $isProtectedContentAvailable {
        get => $this->staticResourceDisposition->isProtectedContentAvailable;
    }
    #[Override]
    public Response $response {
        /**
         * @throws Exception
         */
        get {
            try {
                $body = null;
                $request = $this->request;
                $this->allowedMethods->containsElement($request->httpMethod) ?: throw new MethodNotAllowedException();
                if ($this->isSessionEnabled) {
                    $this->session->start();
                }
                $headers = new Dictionary();
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
                } elseif (!$this->staticResourceDisposition->allowEmptyResponse) {
                    throw new NotFoundException();
                } else {
                    $headers["Content-Type"] = $contentType;
                }
                if ($this->staticResourceDisposition->cacheable) {
                    $headers["Cache-Control"] = "public, max-age=31536000, s-maxage=31536000, immutable";
                }
                return new CORSResponseDecorator(new ResponseHeaderSanitizerDecorator(new Response($request->url, $this->statusCode, $headers, $body))->response, $request, $this->corsPolicy)->response;
            } finally {
                if ($this->isSessionEnabled) {
                    $this->session->commit();
                }
            }
        }
    }
}
