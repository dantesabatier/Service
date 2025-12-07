<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/** @internal */
class Authenticator
{
    public Authentication $authentication {
        get => $this->authentication ??= $this->resolveAuthentication();
    }
    public bool $isProtectedContentAvailable {
        /**
         * @throws Exception
         */
        get => $this->isProtectedContentAvailable ??= $this->isRequestAuthorized();
    }

    public function __construct(private readonly AuthenticationManager $authenticationManager)
    {
    }

    private function resolveAuthentication(): Authentication
    {
        $authenticationClass = AuthenticationFactory::getAuthenticationClass(AuthenticationFactory::getAuthentications() ?? new ArrayClass(), $this->authenticationManager->request->authorizationHeader->scheme) ?? throw new UnimplementedException();
        return new $authenticationClass($this->authenticationManager->request, $this->authenticationManager->managedObjectContext, $this->authenticationManager->isFirstResponder ? $this->authenticationManager->request->serialization : null, $this->authenticationManager->authenticationService);
    }

    /**
     * @throws Exception
     */
    private function isRequestAuthorized(): bool
    {
        if ($this->authenticationManager->request->isPreflight) {
            return true;
        }
        return $this->authentication->isValid && $this->authenticationManager->authorizationService->isAuthorized($this->authentication->user, $this->authenticationManager->request->url->lastPathComponent, match ($this->authenticationManager->request->httpMethod) {
                HTTPRequestMethod::head, HTTPRequestMethod::get => AuthorizationType::read,
                HTTPRequestMethod::post => AuthorizationType::create,
                HTTPRequestMethod::put, HTTPRequestMethod::patch => AuthorizationType::update,
                HTTPRequestMethod::delete => AuthorizationType::delete,
                default => throw new MethodNotAllowedException()
            }, $this->authenticationManager->managedObjectContext);
    }
}
