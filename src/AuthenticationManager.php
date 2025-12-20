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
    public Authentication $authentication {
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
            if (!($authenticatedUser = $this->authentication->authenticatedUser)) {
                return $this->isProtectedContentAvailable = false;
            }
            return $this->isProtectedContentAvailable = $this->authorizationService->isAuthorized($authenticatedUser, $this->request->url->lastPathComponent, match ($this->request->httpMethod) {
                HTTPRequestMethod::head, HTTPRequestMethod::get => AuthorizationType::read,
                HTTPRequestMethod::post => AuthorizationType::create,
                HTTPRequestMethod::put, HTTPRequestMethod::patch => AuthorizationType::update,
                HTTPRequestMethod::delete => AuthorizationType::delete,
                default => throw new MethodNotAllowedException()
            }, $this->authenticationStrategy instanceof BearerAuthenticationStrategy ? $this->authenticationStrategy->scopes : new ArrayClass(), $this->managedObjectContext);
        }
    }

    public function __construct()
    {
        ApplicationSecurityBootstrap::boot();
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
        $username = $user->username;
        $processInfo = ProcessInfo::processInfo();
        $environment = $processInfo->environment;
        if ($jwtKey = $environment[JWTPrivateKey]) {
            $data["token"] = new JSONWebTokenIssuer(new JSONWebTokenService($jwtKey), new AuthorizationScopeBuilder($this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel ?? fatal_error()), $this->managedObjectContext, new Number($environment[JWTValidityTimeIntervalKey] ?? 1800)->intValue)->issue($user, $this->authenticationStrategy->context);
        } else {
            $session = $this->session;
            $session->regenerateID();
            $session->setValueForKey($username, "user");
            $data["session"] = $session->id;
        }
        $this->data = $data;
    }

    #[Action]
    public function logout(): void
    {
        if (!ProcessInfo::processInfo()->environment[JWTPrivateKey]) {
            $this->session->invalidate();
        }
        $this->statusCode = HTTPStatusCode::noContent;
    }
}
