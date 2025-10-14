<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPRequestMethod;

/**
 * A class responsible for defining and enforcing access policies for resource requests.
 */
abstract class AccessPolicy
{
    /**
     * Determines if authorization is required based on the responder's request.
     *
     * @param Responder $responder The responder containing the request to be evaluated.
     * @return bool Returns true if authorization is required, otherwise false.
     */
    public function isAuthorizationRequired(Responder $responder): bool
    {
        return !$responder->request->isPreflight;
    }

    /**
     * Determines and returns the appropriate authorization type based on the HTTP method of the request.
     *
     * @param Responder $responder The responder instance containing the HTTP request details.
     * @return AuthorizationType The authorization type to be performed on the resource.
     */
    public function authorizationType(Responder $responder): AuthorizationType
    {
        return match ($responder->request->httpMethod) {
            HTTPRequestMethod::head, HTTPRequestMethod::get => AuthorizationType::read,
            HTTPRequestMethod::post => AuthorizationType::create,
            HTTPRequestMethod::put, HTTPRequestMethod::patch => AuthorizationType::update,
            HTTPRequestMethod::delete => AuthorizationType::delete,
            default => throw new MethodNotAllowedException()
        };
    }

    /**
     * Retrieves the resource to be accessed or manipulated.
     *
     * @param Responder $responder An instance containing the request and URL information.
     * @return string The resource to be accessed or manipulated by the request.
     */
    public function resource(Responder $responder): string
    {
        return $responder->request->url->lastPathComponent;
    }

    /**
     * Enforces access control by using the provided Responder and Authenticator to validate and manage access permissions.
     *
     * @param Responder $responder The responder instance used to handle response logic.
     * @param Authenticator $authenticator The authenticator instance used to verify access.
     */
    public function enforceAccess(Responder $responder, Authenticator $authenticator): void
    {
    }

    /**
     * Sets the transaction author based on the HTTP request method and the authenticated user.
     *
     * @param Responder $responder The responder instance containing the context and incoming request.
     * @param Authenticator $authenticator The authenticator instance providing user authentication data.
     */
    public function setTransactionAuthor(Responder $responder, Authenticator $authenticator): void
    {
        $responder->managedObjectContext->transactionAuthor = match ($responder->request->httpMethod) {
            HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::patch, HTTPRequestMethod::delete => $authenticator->authentication->user?->username,
            default => null
        };
    }
}
