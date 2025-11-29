<?php

namespace Sabatier\Service;

/** @internal */
readonly class AccessControl
{
    public function __construct(private AuthorizationService $authorizationService, private AuthenticationManager $authenticationManager, private AccessPolicy $accessPolicy)
    {
    }

    public function validateAccess(Responder $responder): void
    {
        if ($this->accessPolicy->isAuthorizationRequired($responder)) {
            $user = $this->authenticationManager->authentication->user;
            if ($user instanceof Authorizable) {
                $resource = $this->accessPolicy->resource($responder);
                $action = $this->accessPolicy->authorizationType($responder);
                $this->authorizationService->authorize($user, $resource, $action, $responder->managedObjectContext);
            }
        }
        $this->accessPolicy->enforceAccess($responder, $this->authenticationManager);
    }

    public function setTransactionAuthor(Responder $responder): void
    {
        $this->accessPolicy->setTransactionAuthor($responder, $this->authenticationManager);
    }
}
