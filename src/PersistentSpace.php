<?php

declare(strict_types=1);

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
                $idempotencyKey = $this->idempotencyKey;
                if ($idempotencyKey !== null) {
                    if ($stored = $this->idempotentResponse) {
                        if ($stored->isProcessing) {
                            throw new ConflictException();
                        }
                        return new ResponsePipeline($this->infrastructureTransformers, $this->transformerContext)->process(new Response($this->request->url, $stored->statusCode, $stored->headers, $stored->body));
                    }
                    $this->markInFlight($idempotencyKey);
                }
                $userResponse = new ResponsePipeline(new Set([JSONTransformer::class, ResponseHeaderSanitizerTransformer::class]), $this->transformerContext)->process(new PersistentSpaceResponseStrategyResolver($this->request, $this->entity, $this->managedObjectContext, $this->fieldSecurityPolicy)->strategy->response);
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
