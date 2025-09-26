<?php

namespace Sabatier\Service;

use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/**
 * Defines an access control policy to handle authorization for specific actions or resources.
 */
abstract class AccessPolicy
{
    /**
     * Determines if the given request should be checked based on its content and context.
     *
     * @param Request $request The incoming request to evaluate for authorization.
     * @return bool Returns true if the request should be checked for authorization, false otherwise.
     */
    public function shouldCheck(Request $request): bool
    {
        return !$request->isPreflight;
    }

    /**
     * Executes an action based on the provided HTTP request method.
     *
     * @param string $method The HTTP request method.
     * @return AuthorizationType The type of authorization determined by the action.
     */
    public function action(#[ExpectedValues(valuesFromClass: HTTPRequestMethod::class)] string $method): AuthorizationType
    {
        return match ($method) {
            HTTPRequestMethod::head, HTTPRequestMethod::get => AuthorizationType::read,
            HTTPRequestMethod::post => AuthorizationType::create,
            HTTPRequestMethod::put, HTTPRequestMethod::patch => AuthorizationType::update,
            HTTPRequestMethod::delete => AuthorizationType::delete,
            default => throw new MethodNotAllowedException()
        };
    }

    /**
     * Handles the resource request and processes it to return the appropriate response.
     *
     * @param Request $request The HTTP request instance containing all the necessary input data.
     * @return string The processed response as a string.
     */
    public function resource(Request $request): string
    {
        return $request->url->lastPathComponent;
    }

    /**
     * Enforces protection for the requested content by using the provided responder and access manager.
     *
     * @param Request $request The request object containing details of the content request.
     * @param Responder $responder The responder responsible for handling the content delivery.
     * @param AccessManager $accessManager The access manager to verify and enforce access rules.
     */
    public function enforceProtectedContent(Request $request, Responder $responder, AccessManager $accessManager): void
    {
    }

    /**
     * Applies the transaction author using the provided user, context, and HTTP request method.
     *
     * @param Authenticatable|null $user The user initiating the transaction, or null if the user is not authenticated.
     * @param ManagedObjectContext $context The context managing the lifecycle of objects involved in the transaction.
     * @param string $method The HTTP method expected for the transaction, constrained by possible values from the HTTPRequestMethod class.
     */
    public function applyTransactionAuthor(?Authenticatable $user, ManagedObjectContext $context, #[ExpectedValues(valuesFromClass: HTTPRequestMethod::class)] string $method): void
    {
        $context->transactionAuthor = match ($method) {
            HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::patch, HTTPRequestMethod::delete => $user?->username,
            default => null
        };
    }
}
