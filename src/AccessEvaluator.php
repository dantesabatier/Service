<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;

/**
 * Defines a contract for objects that evaluate whether a request has access to a resource.
 */
interface AccessEvaluator
{
    /**
     * Evaluates whether the current request has access to a resource.
     *
     * This method is called to determine access permissions based on the provided context.
     *
     * @param Request $request The current HTTP request.
     * @param Authentication $authentication The current authentication object containing user identity and scopes.
     * @param Session $session The session object, used for session-based authentication.
     * @param Dictionary<mixed> $environment The environment variables dictionary, typically from the process or server environment.
     * @param AuthorizationService $authorizationService The service responsible for checking resource-based authorization.
     * @param ManagedObjectContext $managedObjectContext The persistence context, used for fetching or validating user and scope data.
     * @return bool True if access is allowed, false otherwise.
     * @throws Exception If an error occurs during evaluation, e.g., retrieving the authenticated user or checking authorization.
     */
    public function evaluate(Request $request, Authentication $authentication, Session $session, Dictionary $environment, AuthorizationService $authorizationService, ManagedObjectContext $managedObjectContext): bool;
}
