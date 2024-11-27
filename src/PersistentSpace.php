<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Sabatier\CoreData\EntityDescription;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

class PersistentSpace extends Responder
{
    public EntityDescription $entity {
        get => $this->entity ??= $this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->valueForKey($this->request->url->lastPathComponent) ?? throw new NotFoundException("The requested entity \"{$this->request->url->lastPathComponent}\" does not exist.");
    }
    public bool $isFirstResponder {
        get => $this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->offsetExists($this->request->url->lastPathComponent) ?? false;
    }
    public Response $response {
        get => match ($this->request->httpMethod) {
            HTTPRequestMethod::get => new PersistentSpaceRespondentReader($this)->response,
            HTTPRequestMethod::post => new PersistentSpaceRespondentCreator($this)->response,
            HTTPRequestMethod::patch => new PersistentSpaceRespondentUpdater($this)->response,
            HTTPRequestMethod::delete => new PersistentSpaceRespondentDeleter($this)->response,
            HTTPRequestMethod::options => new Response($this),
            default => throw new MethodNotAllowedException()
        };
    }
}
