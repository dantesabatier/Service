<?php

namespace Sabatier\Service;

/**
 * The AccessControl class enforces authorization and access policies within the application, ensuring secure interaction with resources.
 *
 * This class manages the authorization process by leveraging the provided AuthorizationService, AccessManager, PersistentContainer, and AccessPolicy.
 * It evaluates access requests against defined policies and enforces protected content settings as needed.
 */
readonly class AccessControl
{
    public function __construct(private AuthorizationService $authorizationService, private AccessManager $accessManager, private AccessPolicy $policy)
    {
    }

    /**
     * Processes an authorization check based on the given request and responder.
     * If authorization is required, the user's access is validated for a specific resource and action.
     *
     * @param Request $request The incoming request containing necessary data for authorization.
     * @param Responder $responder The responder used to enforce access policies or handle protected content.
     */
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

    /**
     * Sets the transaction author using the provided request object.
     *
     * @param Request $request The request containing the HTTP method used to determine the transaction author.
     */
    public function setTransactionAuthor(Request $request): void
    {
        $this->policy->applyTransactionAuthor($this->accessManager->authentication->user, $this->accessManager->managedObjectContext, $request->httpMethod);
    }
}
