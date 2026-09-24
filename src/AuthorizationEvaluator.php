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
        return Application::shared()->accessPolicy->allowsAccess(($pathComponents = $context->request->url->pathComponents)->count > 1 ? $pathComponents[1] : $context->request->url->lastPathComponent, match ($context->request->httpMethod) {
            HTTPRequestMethod::head, HTTPRequestMethod::get => AuthorizationType::read,
            HTTPRequestMethod::post => AuthorizationType::create,
            HTTPRequestMethod::put, HTTPRequestMethod::patch => AuthorizationType::update,
            HTTPRequestMethod::delete => AuthorizationType::delete,
            default => throw new MethodNotAllowedException()
        }, $context->authentication->authenticatedUser, $context->authentication->authorizationScopes, $context->authorizationService, $context->managedObjectContext);
    }
}
