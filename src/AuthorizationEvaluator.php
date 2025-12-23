<?php

namespace Sabatier\Service;

use Override;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/** @internal */
final class AuthorizationEvaluator implements AccessEvaluator
{
    #[Override]
    public function evaluate(Request $request, Authentication $authentication, Session $session, Dictionary $environment, AuthorizationService $authorizationService, ManagedObjectContext $managedObjectContext): bool
    {
        if (!($user = $authentication->authenticatedUser)) {
            return false;
        }
        return $authorizationService->isAuthorized($user, $request->url->lastPathComponent, match ($request->httpMethod) {
            HTTPRequestMethod::head, HTTPRequestMethod::get => AuthorizationType::read,
            HTTPRequestMethod::post => AuthorizationType::create,
            HTTPRequestMethod::put, HTTPRequestMethod::patch => AuthorizationType::update,
            HTTPRequestMethod::delete => AuthorizationType::delete,
            default => throw new MethodNotAllowedException()
        }, $authentication->authorizationScopes, $managedObjectContext);
    }
}
