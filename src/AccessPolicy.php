<?php

namespace Sabatier\Service;

use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\CoreData\PersistentContainer;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/**
 * Defines an access control policy to handle authorization for specific actions or resources.
 */
interface AccessPolicy
{
    /**
     * Determines if the given request should be authorized based on its content and context.
     *
     * @param Request $request The incoming request to evaluate for authorization.
     * @return bool Returns true if the request is authorized, false otherwise.
     */
    public function shouldAuthorize(Request $request): bool;

    /**
     * Executes an action based on the provided HTTP request method.
     *
     * @param string $method The HTTP request method.
     * @return AuthorizationType The type of authorization determined by the action.
     */
    public function action(#[ExpectedValues(valuesFromClass: HTTPRequestMethod::class)] string $method): AuthorizationType;

    /**
     * Handles the resource request and processes it to return the appropriate response.
     *
     * @param Request $request The HTTP request instance containing all the necessary input data.
     * @return string The processed response as a string.
     */
    public function resource(Request $request): string;

    /**
     * Enforces protection for the requested content by using the provided responder and access manager.
     *
     * @param Responder $firstResponder The responder responsible for handling the content delivery.
     * @param AccessManager $accessManager The access manager to verify and enforce access rules.
     * @param Request $request The request object containing details of the content request.
     */
    public function enforceProtectedContent(Responder $firstResponder, AccessManager $accessManager, Request $request): void;

    /**
     * Applies a transaction author logic using the provided user, persistent container, and HTTP request method.
     *
     * @param Authenticatable|null $user The user object representing the author of the transaction. Can be null for non-mutating requests.
     * @param PersistentContainer $persistentContainer The container responsible for managing persistent storage during the transaction.
     * @param string $method The HTTP request method used for the transaction.
     */
    public function applyTransactionAuthor(?Authenticatable $user, PersistentContainer $persistentContainer, #[ExpectedValues(valuesFromClass: HTTPRequestMethod::class)] string $method): void;
}
