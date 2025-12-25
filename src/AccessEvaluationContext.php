<?php

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;

/**
 * Bundles all information required to evaluate whether a request
 * is allowed to access a resource.
 *
 * This context object centralizes and transports the inputs commonly
 * used during access evaluation. Introducing this type prevents
 * signature inflation of evaluators over time and allows extensions
 * to the access-evaluation pipeline without breaking existing
 * implementations.
 *
 * Typical usage of this context includes:
 * - resolving the authenticated identity
 * - checking resource-based authorization
 * - retrieving persisted user and permission data
 * - reading environment-driven settings
 */
readonly class AccessEvaluationContext
{
    /**
     * Creates a new context that aggregates all relevant access-evaluation inputs.
     *
     * @param Request $request The incoming HTTP request to evaluate.
     * @param Authentication $authentication The authentication state associated with the request, representing the caller's identity and authentication method. May represent anonymous access depending on configuration.
     * @param Session $session The session associated with the request, used primarily for session-based authentication and token exchange.
     * @param Dictionary<mixed> $environment A dictionary of execution-environment values. Typically populated from the host process environment or server runtime.
     * @param AuthorizationService $authorizationService The authorization service responsible for resource-level permission checks during access evaluation.
     * @param ManagedObjectContext $managedObjectContext The persistence context used to retrieve identity and permission information from storage during evaluation.
     */
    public function __construct(public Request $request, public Authentication $authentication, public Session $session, public Dictionary $environment, public AuthorizationService $authorizationService, public ManagedObjectContext $managedObjectContext)
    {
    }
}
