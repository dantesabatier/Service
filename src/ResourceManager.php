<?php

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;

/** @internal */
final class ResourceManager extends Responder
{
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::head, HTTPRequestMethod::get]);
    }
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedHeaders {
        get => new ArrayClass(["Content-Type"]);
    }
    private URL $resourceURL {
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
        get => $this->isProtectedContentAvailable ??= $this->staticResourceDisposition->isProtectedContentAvailable;
    }
    /** @var Set<class-string<ResponseDecorator>> */
    #[Override]
    protected Set $decorators {
        get {
            if (isset($this->decorators)) {
                return $this->decorators;
            }
            /** @var Set<class-string<ResponseDecorator>> $decorators */
            $decorators = new Set([ContentTypeDecorator::class]);
            if ($this->staticResourceDisposition->cacheable) {
                $decorators->insert(CacheHeaderDecorator::class);
            }
            return $this->decorators = $decorators;
        }
    }
    private bool $isDataResolved = false;
    #[Override]
    protected mixed $data {
        /**
         * @throws Exception
         */
        get {
            if ($this->isDataResolved) {
                return $this->data;
            }
            if ($this->request->httpMethod === HTTPRequestMethod::head) {
                return $this->data = null;
            }
            $path = $this->resourceURL->path;
            $fileManager = FileManager::default();
            if ($fileManager->isReadableFile($path)) {
                return $this->data = $fileManager->contents($path) ?? throw new InternalServerErrorException();
            }
            $this->staticResourceDisposition->allowEmptyResponse ?: throw new NotFoundException("The requested URL was not found on this server $this->resourceURL");
            return $this->data = null;
        }
        set {
            $this->isDataResolved = true;
            $this->data = $value;
        }
    }
}
