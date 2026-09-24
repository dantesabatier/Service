<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;

/** @internal */
final class AuthorizationEvaluator implements AuthorizationAccessEvaluator
{
    #[Override]
    public function evaluate(AccessEvaluationContext $context): bool
    {
        return Application::shared()->accessPolicy->allowsAccess(($pathComponents = $context->request->url->pathComponents)->count > 1 ? $pathComponents[1] : $context->request->url->lastPathComponent, AuthorizationType::forHTTPMethod($context->request->httpMethod), $context->authentication->authenticatedUser, $context->authentication->authorizationScopes, $context->authorizationService, $context->managedObjectContext);
    }
}
