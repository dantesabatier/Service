<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPRequestMethod;

/**
 * A class responsible for defining and enforcing access policies for resource requests.
 */
abstract class AccessPolicy
{
    public bool $isAuthorizationRequired {
        get => !$this->responder->request->isPreflight;
    }
    public AuthorizationType $authorizationType {
        get => match ($this->responder->request->httpMethod) {
            HTTPRequestMethod::head, HTTPRequestMethod::get => AuthorizationType::read,
            HTTPRequestMethod::post => AuthorizationType::create,
            HTTPRequestMethod::put, HTTPRequestMethod::patch => AuthorizationType::update,
            HTTPRequestMethod::delete => AuthorizationType::delete,
            default => throw new MethodNotAllowedException()
        };
    }
    public string $resource {
        get => $this->responder->request->url->lastPathComponent;
    }

    public function __construct(public readonly Responder $responder, public readonly AuthenticationService $authenticationService)
    {
    }

    /**
     * Enforces access control to ensure the current user has the necessary permissions.
     */
    public function enforceAccess(): void
    {
    }

    /**
     * Sets the author of the transaction based on the HTTP request method.
     */
    public function setTransactionAuthor(): void
    {
        $this->responder->managedObjectContext->transactionAuthor = match ($this->responder->request->httpMethod) {
            HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::patch, HTTPRequestMethod::delete => $this->authenticationService->authentication->user?->username,
            default => null
        };
    }
}
