<?php

namespace Sabatier\Service;

use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\CoreData\PersistentContainer;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/** @internal */
class DefaultAccessPolicy implements AccessPolicy
{
    public function shouldAuthorize(Request $request): bool
    {
        return !$request->isPreflight;
    }

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

    public function resource(Request $request): string
    {
        return $request->url->lastPathComponent;
    }

    public function enforceProtectedContent(Responder $firstResponder, AccessManager $accessManager, Request $request): void
    {
        if ($firstResponder->isProtectedContentAvailable || $accessManager->isProtectedContentAvailable) {
            return;
        }
        if ($accessManager->authentication->isValid) {
            throw new ForbiddenException(match ($request->httpMethod) {
                HTTPRequestMethod::get => "You don't have permission to access this resource.",
                default => "You don't have permission to perform this action."
            });
        }
        throw new UnauthorizedException();
    }

    public function applyTransactionAuthor(?Authenticatable $user, PersistentContainer $persistentContainer, #[ExpectedValues(valuesFromClass: HTTPRequestMethod::class)] string $method): void
    {
        $persistentContainer->viewContext->transactionAuthor = match ($method) {
            HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::patch, HTTPRequestMethod::delete => $user?->username,
            default => null
        };
    }
}
