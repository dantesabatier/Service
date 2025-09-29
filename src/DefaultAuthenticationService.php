<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;

/** @internal */
class DefaultAuthenticationService implements AuthenticationService
{
    public Authentication $authentication {
        get => $this->authentication ??= $this->resolveAuthentication();
    }
    public bool $isProtectedContentAvailable {
        get => $this->isProtectedContentAvailable ??= $this->isRequestAuthorized();
    }

    public function __construct(private readonly Responder $responder)
    {
    }

    private function resolveAuthentication(): Authentication
    {
        $authenticationClass = AuthenticationFactory::getAuthenticationClass(AuthenticationFactory::getAuthentications() ?? new ArrayClass(), $this->responder->request->authorizationHeader->scheme) ?? throw new UnimplementedException();
        return new $authenticationClass($this->responder->request, $this->responder->managedObjectContext, $this->responder->isFirstResponder ? $this->responder->request->serialization : null);
    }

    private function isRequestAuthorized(): bool
    {
        if ($this->responder->request->isPreflight) {
            return true;
        }
        if (!$this->authentication->isValid) {
            return false;
        }
        return $this->authentication->user?->authorization instanceof Authorization;
    }
}
