<?php

namespace Sabatier\Service;

/** @internal */
readonly class AccessControl
{
    public function __construct(private AuthorizationService $authorizationService, private AuthenticationService $authenticationService, private AccessPolicy $policy)
    {
    }

    public function check(Request $request, Responder $responder): void
    {
        if ($this->policy->shouldCheck($request)) {
            $user = $this->authenticationService->authentication->user;
            if ($user instanceof Authorizable) {
                $resource = $this->policy->resource($request);
                $action = $this->policy->action($request->httpMethod);
                $this->authorizationService->authorize($user, $resource, $action, $this->authenticationService->managedObjectContext);
            }
        }
        $this->policy->enforceProtectedContent($request, $responder, $this->authenticationService);
    }

    public function setTransactionAuthor(Request $request): void
    {
        $this->policy->applyTransactionAuthor($request, $this->authenticationService->authentication->user, $this->authenticationService->managedObjectContext);
    }
}
