<?php

namespace Sabatier\Service;

/** @internal */
readonly class AccessControl
{
    public function __construct(private AuthorizationService $authorizationService, private AccessManager $accessManager, private AccessPolicy $policy)
    {
    }

    public function check(Request $request, Responder $responder): void
    {
        if ($this->policy->shouldCheck($request)) {
            $user = $this->accessManager->authentication->user;
            if ($user instanceof Authorizable) {
                $resource = $this->policy->resource($request);
                $action = $this->policy->action($request->httpMethod);
                $this->authorizationService->authorize($user, $resource, $action, $this->accessManager->managedObjectContext);
            }
        }
        $this->policy->enforceProtectedContent($request, $responder, $this->accessManager);
    }

    public function setTransactionAuthor(Request $request): void
    {
        $this->policy->applyTransactionAuthor($request, $this->accessManager->authentication->user, $this->accessManager->managedObjectContext);
    }
}
