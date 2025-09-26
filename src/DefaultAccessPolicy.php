<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/** @internal */
final class DefaultAccessPolicy extends AccessPolicy
{
    #[Override]
    public function enforceProtectedContent(Request $request, Responder $responder, AccessManager $accessManager): void
    {
        if ($responder->isProtectedContentAvailable || $accessManager->isProtectedContentAvailable) {
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
}
