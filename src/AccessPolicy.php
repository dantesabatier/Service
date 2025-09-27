<?php

namespace Sabatier\Service;

use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/**
 * Defines an access control policy to handle authorization for specific actions or resources.
 */
class AccessPolicy
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
     * Enforces protection for the requested content by using the provided responder and authentication service.
     *
     * @param Request $request The request object containing details of the content request.
     * @param Responder $responder The responder responsible for handling the content delivery.
     * @param AuthenticationService $authenticationService The authentication service to verify and enforce access rules.
     */
    public function enforceProtectedContent(Request $request, Responder $responder, AuthenticationService $authenticationService): void
    {
        if ($responder->isProtectedContentAvailable || $authenticationService->isProtectedContentAvailable) {
            return;
        }
        if ($authenticationService->authentication->isValid) {
            throw new ForbiddenException(match ($request->httpMethod) {
                HTTPRequestMethod::get => "You don't have permission to access this resource.",
                default => "You don't have permission to perform this action."
            });
        }
        throw new UnauthorizedException();
    }

    /**
     * Applies the transaction author to the given managed object context based on the HTTP method of the request.
     *
     * @param Request $request The request object containing HTTP method details.
     * @param Authenticatable|null $user An optional user object representing the authenticated user.
     * @param ManagedObjectContext $context The managed object context to which the transaction author is applied.
     */
    public function applyTransactionAuthor(Request $request, ?Authenticatable $user, ManagedObjectContext $context): void
    {
        $context->transactionAuthor = match ($request->httpMethod) {
            HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::patch, HTTPRequestMethod::delete => $user?->username,
            default => null
        };
    }
}
