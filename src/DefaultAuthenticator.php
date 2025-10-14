<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;

/** @internal */
final class DefaultAuthenticator extends Authenticator
{
    private(set) Authentication $authentication {
        get => $this->authentication ??= $this->resolveAuthentication();
    }
    private(set) bool $isProtectedContentAvailable {
        get => $this->isProtectedContentAvailable ??= $this->isRequestAuthorized();
    }

    private function resolveAuthentication(): Authentication
    {
        $authenticationClass = AuthenticationFactory::getAuthenticationClass(AuthenticationFactory::getAuthentications() ?? new ArrayClass(), $this->protocol->request->authorizationHeader->scheme) ?? throw new UnimplementedException();
        return new $authenticationClass($this->protocol->request, $this->protocol->managedObjectContext, $this->protocol->isFirstResponder ? $this->protocol->request->serialization : null, $this->protocol->authenticationService);
    }

    private function isRequestAuthorized(): bool
    {
        if ($this->protocol->request->isPreflight) {
            return true;
        }
        if (!$this->authentication->isValid) {
            return false;
        }
        return $this->authentication->user?->authorization instanceof Authorization;
    }
}
