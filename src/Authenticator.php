<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;

/** @internal */
class Authenticator
{
    public Authentication $authentication {
        get => $this->authentication ??= $this->resolveAuthentication();
    }
    public bool $isProtectedContentAvailable {
        get => $this->isProtectedContentAvailable ??= $this->isRequestAuthorized();
    }

    public function __construct(public readonly AuthenticationManager $authenticationManager)
    {
    }

    private function resolveAuthentication(): Authentication
    {
        $authenticationClass = AuthenticationFactory::getAuthenticationClass(AuthenticationFactory::getAuthentications() ?? new ArrayClass(), $this->authenticationManager->request->authorizationHeader->scheme) ?? throw new UnimplementedException();
        return new $authenticationClass($this->authenticationManager->request, $this->authenticationManager->managedObjectContext, $this->authenticationManager->isFirstResponder ? $this->authenticationManager->request->serialization : null, $this->authenticationManager->authenticationService);
    }

    private function isRequestAuthorized(): bool
    {
        if ($this->authenticationManager->request->isPreflight) {
            return true;
        }
        if (!$this->authentication->isValid) {
            return false;
        }
        return $this->authentication->user?->authorization instanceof Authorization;
    }
}
