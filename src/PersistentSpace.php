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
    public Respondent $respondent {
        get => match ($this->request->httpMethod) {
            HTTPRequestMethod::get => new PersistentSpaceRespondentReader($this),
            HTTPRequestMethod::post => new PersistentSpaceRespondentCreator($this),
            HTTPRequestMethod::patch => new PersistentSpaceRespondentUpdater($this),
            HTTPRequestMethod::delete => new PersistentSpaceRespondentDeleter($this),
            HTTPRequestMethod::options => new PersistentSpaceRespondent($this),
            default => throw new MethodNotAllowedException()
        };
    }
    public Response $response {
        get => $this->respondent->response;
    }
}
