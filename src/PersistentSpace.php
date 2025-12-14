<?php

namespace Sabatier\Service;

use JsonException;
use Sabatier\CoreData\EntityDescription;

/** @internal */
class PersistentSpace extends Responder
{
    private(set) EntityDescription $entity {
        get => $this->entity ??= $this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->valueForKey($this->request->url->lastPathComponent) ?? throw new NotFoundException("The requested entity \"{$this->request->url->lastPathComponent}\" does not exist.");
    }
    public bool $isFirstResponder {
        get => (bool)$this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->offsetExists($this->request->url->lastPathComponent);
    }
    public Response $response {
        /**
         * @throws JsonException
         */
        get => new CORSResponseDecorator(new JSONDecorator(new PersistentSpaceResponseStrategyResolver($this->request, $this->entity, $this->managedObjectContext)->strategy->response)->response, $this->request)->response;
    }
}
