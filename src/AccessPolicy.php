<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPRequestMethod;

/**
 * A class responsible for defining and enforcing access policies for resource requests.
 */
abstract class AccessPolicy
{
    /**
     * Enforces access control based on the provided responder and authentication manager.
     *
     * @param Responder $firstResponder The responder instance that handles the request.
     * @param AuthenticationManager $authenticationManager The authentication manager responsible for user authentication.
     */
    abstract public function enforceAccess(Responder $firstResponder, AuthenticationManager $authenticationManager): void;

    /**
     * Sets the transaction author based on the HTTP method of the request and user authentication context.
     *
     * @param Responder $responder The responder instance that provides context for the request and response handling.
     * @param AuthenticationManager $authenticationManager The authentication manager responsible for accessing user authentication details.
     */
    public function setTransactionAuthor(Responder $responder, AuthenticationManager $authenticationManager): void
    {
        $responder->managedObjectContext->transactionAuthor = match ($responder->request->httpMethod) {
            HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::patch, HTTPRequestMethod::delete => $authenticationManager->authentication->user?->username,
            default => null
        };
    }
}
