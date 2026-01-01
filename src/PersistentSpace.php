<?php

namespace Sabatier\Service;

use Sabatier\CoreData\EntityDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final class PersistentSpace extends Responder
{
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::patch, HTTPRequestMethod::delete]);
    }
    private(set) EntityDescription $entity {
        get => $this->entity ??= $this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->valueForKey($this->request->url->lastPathComponent) ?? throw new NotFoundException();
    }
    public AuthorizationContext $authorizationContext {
        get {
            $authentication = Application::shared()->authenticationManager->authentication;
            return new AuthorizationContext($authentication->authorizationScopes, $authentication->authenticatedUser ?? fatal_error());
        }
    }
    public bool $isFirstResponder {
        get => (bool)$this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->offsetExists($this->request->url->lastPathComponent);
    }
    public Response $response {
        get {
            try {
                if ($this->isSessionEnabled) {
                    $this->session->start();
                }
                return new CORSResponseDecorator(new ResponseHeaderSanitizerDecorator(new JSONDecorator(new PersistentSpaceResponseStrategyResolver($this->request, $this->entity, $this->managedObjectContext, $this->authorizationContext)->strategy->response)->response)->response, $this->request, $this->corsPolicy)->response;
            } finally {
                if ($this->isSessionEnabled) {
                    $this->session->commit();
                }
            }
        }
    }
}
