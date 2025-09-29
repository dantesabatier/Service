<?php

namespace Sabatier\Service;

/** @internal */
readonly class AccessControl
{
    public function __construct(private AuthorizationService $authorizationService, private AuthenticationService $authenticationService, private AccessPolicy $accessPolicy)
    {
    }

    public function validateAccess(Responder $responder): void
    {
        if ($this->accessPolicy->isAuthorizationRequired($responder)) {
            $user = $this->authenticationService->authentication->user;
            if ($user instanceof Authorizable) {
                $resource = $this->accessPolicy->resource($responder);
                $action = $this->accessPolicy->authorizationType($responder);
                $this->authorizationService->authorize($user, $resource, $action, $responder->managedObjectContext);
            }
        }
        $this->accessPolicy->enforceAccess($responder, $this->authenticationService);
    }

    public function setTransactionAuthor(Responder $responder): void
    {
        $this->accessPolicy->setTransactionAuthor($responder, $this->authenticationService);
    }
}
