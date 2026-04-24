<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\ProcessInfo;

/**
 * Abstract base class for enforcing access control on incoming requests.
 *
 * `Application` holds a single `AccessPolicy` instance (defaulting to `DefaultAccessPolicy`)
 * and calls `enforceAccess` before the first responder produces its response. Implementations
 * may throw `UnauthorizedException` or `ForbiddenException` to short-circuit the request.
 *
 * `setTransactionAuthor` is a concrete convenience that wires the authenticated username (or the
 * process name for unauthenticated mutation requests) into the Core Data managed object context,
 * so persistent history records the author of every write.
 *
 * ## Built-in implementations
 * - `DefaultAccessPolicy` — enforces authentication for all non-public routes.
 * - `PublicAccessPolicy` — allows unauthenticated access to all routes.
 *
 * Override `Application::$accessPolicy` in the application delegate to supply a custom policy.
 *
 * @see DefaultAccessPolicy
 * @see PublicAccessPolicy
 * @see Application::$accessPolicy
 */
abstract class AccessPolicy
{
    /**
     * Enforces access control based on the provided responder and authentication manager.
     *
     * @param Responder $responder The responder instance that handles the request.
     * @param AuthenticationManager $authenticationManager The authentication manager responsible for user authentication.
     */
    abstract public function enforceAccess(Responder $responder, AuthenticationManager $authenticationManager): void;

    /**
     * Sets the transaction author based on the HTTP method of the request and user authentication context.
     *
     * @param Responder $responder The responder instance that provides context for the request and response handling.
     * @param AuthenticationManager $authenticationManager The authentication manager responsible for accessing user authentication details.
     */
    public function setTransactionAuthor(Responder $responder, AuthenticationManager $authenticationManager): void
    {
        $responder->managedObjectContext->transactionAuthor = match ($responder->request->httpMethod) {
            HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::patch, HTTPRequestMethod::delete => $authenticationManager->authentication->authenticatedUser?->username ?? ProcessInfo::processInfo()->processName,
            default => null
        };
    }
}
