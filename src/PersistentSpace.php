<?php

namespace Sabatier\Service;

use Override;
use Sabatier\CoreData\EntityDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/** @internal */
final class PersistentSpace extends Responder
{
    /** @var ArrayClass<string> */
    #[Override]
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::patch, HTTPRequestMethod::delete]);
    }
    private(set) EntityDescription $entity {
        get => $this->entity ??= $this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->valueForKey($this->request->url->lastPathComponent) ?? throw new NotFoundException();
    }
    public AuthorizationContext $authorizationContext {
        get {
            $authentication = Application::shared()->authenticationManager->authentication;
            return new AuthorizationContext($authentication->authenticatedUser, $authentication->authorizationScopes, $this->isSecurityEnabled);
        }
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
                return new CORSResponseDecorator(new ResponseHeaderSanitizerDecorator(new JSONDecorator(new PersistentSpaceResponseStrategyResolver($this->request, $this->entity, $this->managedObjectContext, $this->authorizationContext)->strategy->response)->response)->response, $this->request, $this->corsPolicy)->response;
            } finally {
                if ($this->isSessionEnabled) {
                    $this->session->commit();
                }
            }
        }
    }
}
