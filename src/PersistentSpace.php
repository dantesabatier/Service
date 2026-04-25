<?php

namespace Sabatier\Service;

use Override;
use Sabatier\CoreData\EntityDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
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
                $idempotencyKey = $this->resolveIdempotencyKey($this->request);
                if (($idempotencyKey !== null) && ($stored = Application::shared()->idempotencyStore->get($idempotencyKey))) {
                    return new ResponsePipeline($this->infrastructureTransformers, $this->transformerContext)->process(new Response($this->request->url, $stored->statusCode, new Dictionary($stored->headers), $stored->body));
                }
                $userResponse = new ResponsePipeline(new Set([JSONTransformer::class, ResponseHeaderSanitizerTransformer::class]), $this->transformerContext)->process( new PersistentSpaceResponseStrategyResolver($this->request, $this->entity, $this->managedObjectContext, $this->fieldSecurityPolicy)->strategy->response);
                if ($idempotencyKey !== null) {
                    $this->storeIdempotentResponse($idempotencyKey, $userResponse);
                }
                return new ResponsePipeline($this->infrastructureTransformers, $this->transformerContext)->process($userResponse);
            } finally {
                if ($this->isSessionEnabled) {
                    $this->session->commit();
                }
            }
        }
    }
}
