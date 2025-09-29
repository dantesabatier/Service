<?php

namespace Sabatier\Service;

/** @internal */
readonly class AccessControl
{
    public function __construct(private AuthorizationService $authorizationService, private AuthenticationService $authenticationService, private AccessPolicy $accessPolicy)
    {
    }

    public function check(Request $request, Responder $responder): void
    {
        if ($this->accessPolicy->isAuthorizationRequired($request)) {
            $user = $this->authenticationService->authentication->user;
            if ($user instanceof Authorizable) {
                $resource = $this->accessPolicy->resource($request);
                $action = $this->accessPolicy->authorizationType($request);
                $this->authorizationService->authorize($user, $resource, $action, $this->authenticationService->managedObjectContext);
            }
        }
        $this->accessPolicy->enforceAccess($request, $responder, $this->authenticationService);
    }

    public function setTransactionAuthor(Request $request): void
    {
        $this->accessPolicy->setTransactionAuthor($request, $this->authenticationService->managedObjectContext, $this->authenticationService->authentication->user);
    }
}
