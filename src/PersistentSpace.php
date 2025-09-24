<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Sabatier\CoreData\EntityDescription;

/** @internal */
class PersistentSpace extends Responder
{
    public EntityDescription $entity {
        get => $this->entity ??= $this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->valueForKey($this->request->url->lastPathComponent) ?? throw new NotFoundException("The requested entity \"{$this->request->url->lastPathComponent}\" does not exist.");
    }
    public bool $isFirstResponder {
        get => (bool)$this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->offsetExists($this->request->url->lastPathComponent);
    }
    public Response $response {
        get => new PersistentSpaceResponseStrategyResolver($this)->strategy->response;
    }
}
