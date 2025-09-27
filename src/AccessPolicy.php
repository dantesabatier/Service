<?php

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/**
 * A class responsible for defining and enforcing access policies for resource requests.
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
     * Determines the type of authorization required based on the HTTP method of the request.
     *
     * @param Request $request The request object containing the HTTP method and other request details.
     * @return AuthorizationType The type of authorization corresponding to the HTTP method.
     */
    public function action(Request $request): AuthorizationType
    {
        return match ($request->httpMethod) {
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
     * @param ManagedObjectContext $context The managed object context to which the transaction author is applied.
     * @param Authenticatable|null $user An optional user object representing the authenticated user.
     */
    public function applyTransactionAuthor(Request $request, ManagedObjectContext $context, ?Authenticatable $user): void
    {
        $context->transactionAuthor = match ($request->httpMethod) {
            HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::patch, HTTPRequestMethod::delete => $user?->username,
            default => null
        };
    }
}
