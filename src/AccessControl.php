<?php

namespace Sabatier\Service;

/** @internal */
readonly class AccessControl
{
    public function __construct(private AuthorizationService $authorizationService, private AuthenticationService $authenticationService, private AccessPolicy $accessPolicy)
    {
    }

    public function validateAccess(): void
    {
        if ($this->accessPolicy->isAuthorizationRequired) {
            $user = $this->authenticationService->authentication->user;
            if ($user instanceof Authorizable) {
                $resource = $this->accessPolicy->resource;
                $action = $this->accessPolicy->authorizationType;
                $this->authorizationService->authorize($user, $resource, $action, $this->accessPolicy->responder->managedObjectContext);
            }
        }
        $this->accessPolicy->enforceAccess();
    }

    public function setTransactionAuthor(): void
    {
        $this->accessPolicy->setTransactionAuthor();
    }
}
