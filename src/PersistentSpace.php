<?php

namespace Sabatier\Service;

use JsonException;
use Sabatier\CoreData\EntityDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/** @internal */
class PersistentSpace extends Responder
{
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::patch, HTTPRequestMethod::delete]);
    }
    private(set) EntityDescription $entity {
        get => $this->entity ??= $this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->valueForKey($this->request->url->lastPathComponent) ?? throw new NotFoundException();
    }
    public bool $isFirstResponder {
        get => (bool)$this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->offsetExists($this->request->url->lastPathComponent);
    }
    public Response $response {
        /**
         * @throws JsonException
         */
        get {
            error_log("*{$this->request->httpMethod}*{$this->corsPolicy->allowedMethods}*");
            return new CORSResponseDecorator(new ResponseHeaderSanitizerDecorator(new JSONDecorator(new PersistentSpaceResponseStrategyResolver($this->request, $this->entity, $this->managedObjectContext)->strategy->response)->response)->response, $this->request, $this->corsPolicy)->response;
        }
    }
}
