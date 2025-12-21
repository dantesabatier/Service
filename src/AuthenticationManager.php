<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\ProcessInfo;
use function Sabatier\Foundation\fatal_error;

/**
 * An object that manages authentication processes.
 */
class AuthenticationManager extends Responder
{
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::post]);
    }
    private AuthenticationStrategy $authenticationStrategy {
        get {
            if (!isset($this->authenticationStrategy)) {
                $authenticationStrategyClass = AuthenticationStrategyFactory::getAuthenticationStrategyClass(AuthenticationStrategyFactory::getAuthenticationStrategies() ?? new ArrayClass(), $this->request->authorizationHeader->scheme) ?? throw new UnimplementedException();
                $this->authenticationStrategy = new $authenticationStrategyClass(new AuthenticationContext($this->request->authorizationHeader, $this->request->url->host, $this->request->httpMethod, $this->managedObjectContext, $this->isFirstResponder ? $this->request->serialization : null, $this->authenticationService));
            }
            return $this->authenticationStrategy;
        }
    }
    /** @var Authentication The authentication object managing the authentication process. */
    private(set) Authentication $authentication {
        get => $this->authentication ??= new Authentication($this->authenticationStrategy);
    }
    public bool $isProtectedContentAvailable {
        /**
         * @throws Exception
         */
        get {
            if (isset($this->isProtectedContentAvailable)) {
                return $this->isProtectedContentAvailable;
            }
            if ($this->request->isPreflight) {
                return $this->isProtectedContentAvailable = true;
            }
            if (!$this->authentication->isValid) {
                return $this->isProtectedContentAvailable = false;
            }
            if (!$this->isJWTEnabled()) {
                return $this->isProtectedContentAvailable = $this->isSessionAuthenticated();
            }
            if (!($user = $this->authentication->authenticatedUser)) {
                return $this->isProtectedContentAvailable = false;
            }
            return $this->isProtectedContentAvailable = $this->authorizationService->isAuthorized($user, $this->request->url->lastPathComponent, match ($this->request->httpMethod) {
                HTTPRequestMethod::head, HTTPRequestMethod::get => AuthorizationType::read,
                HTTPRequestMethod::post => AuthorizationType::create,
                HTTPRequestMethod::put, HTTPRequestMethod::patch => AuthorizationType::update,
                HTTPRequestMethod::delete => AuthorizationType::delete,
                default => throw new MethodNotAllowedException()
            }, $this->authentication->scopes, $this->managedObjectContext);
        }
    }

    public function __construct()
    {
        ApplicationSecurityBootstrap::boot();
    }

    private function isJWTEnabled(): bool
    {
        return ProcessInfo::processInfo()->environment->offsetExists(JWTPrivateKey);
    }

    private function isSessionAuthenticated(): bool
    {
        return $this->session->isActive && $this->session->valueForKey("authenticated") === true && $this->session->valueForKey("user_id") !== null;
    }

    /**
     * @throws Exception
     */
    #[Action(decorators: [JSONDecorator::class])]
    public function login(): void
    {
        $user = $this->authentication->authenticatedUser ?? throw new UnauthorizedException();
        /** @var Dictionary<mixed> $data */
        $data = new Dictionary();
        $data["user"] = $user;
        $environment = ProcessInfo::processInfo()->environment;
        if ($jwtKey = $environment[JWTPrivateKey]) {
            $data["token"] = new JSONWebTokenIssuer(new JSONWebTokenService($jwtKey, $this->request->url->host), new AuthorizationScopeBuilder($this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel ?? fatal_error()), $this->managedObjectContext, new Number($environment[JWTValidityTimeIntervalKey] ?? 1800)->intValue)->issue($user, $this->authenticationStrategy->context);
        } else {
            $session = $this->session;
            $session->regenerateID();
            $session->setValueForKey($user, "user");
            $session->setValueForKey(true, "authenticated");
        }
        $this->data = $data;
    }

    #[Action]
    public function logout(): void
    {
        $this->session->invalidate();
        $this->statusCode = HTTPStatusCode::noContent;
    }
}
