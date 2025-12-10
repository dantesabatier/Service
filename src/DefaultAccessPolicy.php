<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use function Sabatier\Foundation\localized_string;

/**
 * Represents the default access policy enforcement mechanism.
 */
final class DefaultAccessPolicy extends AccessPolicy
{
    #[Override]
    public function enforceAccess(Responder $responder, AuthenticationManager $authenticationManager): void
    {
        if ($responder->isProtectedContentAvailable || $authenticationManager->isProtectedContentAvailable) {
            return;
        }
        if ($authenticationManager->authentication->isValid) {
            throw new ForbiddenException(match ($responder->request->httpMethod) {
                HTTPRequestMethod::get => localized_string("You don't have permission to access this resource."),
                default => localized_string("You don't have permission to perform this action.")
            });
        }
        throw new UnauthorizedException();
    }
}
