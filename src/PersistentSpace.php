<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Sabatier\CoreData\EntityDescription;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/** @internal */
class PersistentSpace extends Responder
{
    public EntityDescription $entity {
        get => $this->entity ??= $this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->valueForKey($this->request->url->lastPathComponent) ?? throw new NotFoundException("The requested entity \"{$this->request->url->lastPathComponent}\" does not exist.");
    }
    public bool $isFirstResponder {
        get => (bool)$this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->offsetExists($this->request->url->lastPathComponent);
    }
    public Respondent $respondent {
        get {
            $respondentClass = [HTTPRequestMethod::get => PersistentSpaceRespondentReader::class, HTTPRequestMethod::post => PersistentSpaceRespondentCreator::class, HTTPRequestMethod::patch => PersistentSpaceRespondentUpdater::class, HTTPRequestMethod::delete => PersistentSpaceRespondentDeleter::class, HTTPRequestMethod::options => PersistentSpaceRespondentDefault::class][$this->request->httpMethod] ?? throw new MethodNotAllowedException();
            return new $respondentClass($this);
        }
    }
    public Response $response {
        get => $this->respondent->response;
    }
}
