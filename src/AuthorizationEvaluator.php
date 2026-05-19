<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/** @internal */
final class AuthorizationEvaluator implements AuthorizationAccessEvaluator
{
    #[Override]
    public function evaluate(AccessEvaluationContext $context): bool
    {
        if (!($user = $context->authentication->authenticatedUser)) {
            return false;
        }
        return $context->authorizationService->isAuthorized($user, ($pathComponents = $context->request->url->pathComponents)->count > 1 ? (string)$pathComponents[1] : $context->request->url->lastPathComponent, match ($context->request->httpMethod) {
            HTTPRequestMethod::head, HTTPRequestMethod::get => AuthorizationType::read,
            HTTPRequestMethod::post => AuthorizationType::create,
            HTTPRequestMethod::put, HTTPRequestMethod::patch => AuthorizationType::update,
            HTTPRequestMethod::delete => AuthorizationType::delete,
            default => throw new MethodNotAllowedException()
        }, $context->authentication->authorizationScopes, $context->managedObjectContext);
    }
}
