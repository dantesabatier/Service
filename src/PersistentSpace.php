<?php

namespace Sabatier\Service;

use Override;
use Sabatier\CoreData\EntityDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Set;

/** @internal */
final class PersistentSpace extends Responder
{
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::patch, HTTPRequestMethod::delete]);
    }
    private EntityDescription $entity {
        get => $this->entity ??= $this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->valueForKey($this->request->url->lastPathComponent) ?? throw new NotFoundException();
    }
    #[Override]
    public bool $isFirstResponder {
        get => (bool)$this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->offsetExists($this->request->url->lastPathComponent);
    }
    #[Override]
    public Response $response {
        get {
            try {
                $this->allowedMethods->containsElement($this->request->httpMethod) ?: throw new MethodNotAllowedException();
                if ($this->isSessionEnabled) {
                    $this->session->start();
                }
                return new ResponsePipeline(new Set([JSONTransformer::class, ResponseHeaderSanitizerTransformer::class, ConditionalGetTransformer::class, RateLimitHeaderTransformer::class, SecurityHeadersTransformer::class, CORSResponseTransformer::class]), $this->transformerContext)->process(
                    new PersistentSpaceResponseStrategyResolver($this->request, $this->entity, $this->managedObjectContext, $this->fieldSecurityPolicy)->strategy->response
                );
            } finally {
                if ($this->isSessionEnabled) {
                    $this->session->commit();
                }
            }
        }
    }
}
