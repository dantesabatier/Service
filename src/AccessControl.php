<?php

namespace Sabatier\Service;

/** @internal */
readonly class AccessControl
{
    public function __construct(private AuthorizationService $authorizationService, private Authenticator $authenticator, private AccessPolicy $accessPolicy)
    {
    }

    public function validateAccess(Responder $responder): void
    {
        if ($this->accessPolicy->isAuthorizationRequired($responder)) {
            $user = $this->authenticator->authentication->user;
            if ($user instanceof Authorizable) {
                $resource = $this->accessPolicy->resource($responder);
                $action = $this->accessPolicy->authorizationType($responder);
                $this->authorizationService->authorize($user, $resource, $action, $responder->managedObjectContext);
            }
        }
        $this->accessPolicy->enforceAccess($responder, $this->authenticator);
    }

    public function setTransactionAuthor(Responder $responder): void
    {
        $this->accessPolicy->setTransactionAuthor($responder, $this->authenticator);
    }
}
