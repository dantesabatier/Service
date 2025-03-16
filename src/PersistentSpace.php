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
        get => (bool)$this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->offsetExists($this->request->url->lastPathComponent);
    }
    public Response $response {
        get => new (match ($this->request->httpMethod) {
            HTTPRequestMethod::get => PersistentSpaceRespondentReader::class,
            HTTPRequestMethod::post => PersistentSpaceRespondentCreator::class,
            HTTPRequestMethod::patch => PersistentSpaceRespondentUpdater::class,
            HTTPRequestMethod::delete => PersistentSpaceRespondentDeleter::class,
            HTTPRequestMethod::options => PersistentSpaceRespondentDefault::class,
            default => throw new MethodNotAllowedException()
        })($this)->response;
    }
}
