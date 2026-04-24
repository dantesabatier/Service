<?php

namespace Sabatier\Service;

use Override;
use Sabatier\CoreData\EntityDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

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
                return new CORSResponseTransformer(
                    new SecurityHeadersTransformer(
                        new RateLimitHeaderTransformer(
                            new ConditionalGetTransformer(
                                new ResponseHeaderSanitizerTransformer(
                                    new JSONTransformer(
                                        new PersistentSpaceResponseStrategyResolver($this->request, $this->entity, $this->managedObjectContext, $this->fieldSecurityPolicy)->strategy->response
                                    )->response
                                )->response,
                                $this->transformerContext
                            )->response,
                            $this->transformerContext
                        )->response,
                        $this->transformerContext
                    )->response,
                    $this->transformerContext
                )->response;
            } finally {
                if ($this->isSessionEnabled) {
                    $this->session->commit();
                }
            }
        }
    }
}
