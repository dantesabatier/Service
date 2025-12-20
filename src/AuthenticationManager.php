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
    /** @var IdentitySource|null The resolved identity associated with the current request, if any */
    private(set) ?IdentitySource $identitySource {
        get {
            if (isset($this->identitySource)) {
                return $this->identitySource;
            }
            if ($this->authenticationStrategy instanceof BearerAuthenticationStrategy && $this->authenticationStrategy->isValid) {
                return $this->identitySource = new JWTIdentitySource($this->authentication->authenticatedUser, $this->authenticationStrategy->token);
            }
            if ($this->session->isStarted) {
                return $this->identitySource = new SessionIdentitySource($this->authentication->authenticatedUser, $this->session);
            }
            return null;
        }
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
            if (!($authenticatedUser = $this->identitySource?->subject)) {
                return $this->isProtectedContentAvailable = false;
            }
            return $this->isProtectedContentAvailable = $this->authorizationService->isAuthorized($authenticatedUser, $this->request->url->lastPathComponent, match ($this->request->httpMethod) {
                HTTPRequestMethod::head, HTTPRequestMethod::get => AuthorizationType::read,
                HTTPRequestMethod::post => AuthorizationType::create,
                HTTPRequestMethod::put, HTTPRequestMethod::patch => AuthorizationType::update,
                HTTPRequestMethod::delete => AuthorizationType::delete,
                default => throw new MethodNotAllowedException()
            }, $this->identitySource->scopes, $this->managedObjectContext);
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
        $environment = ProcessInfo::processInfo()->environment;
        if ($jwtKey = $environment[JWTPrivateKey]) {
            $service = new JSONWebTokenService($jwtKey);
            $issuer = new JSONWebTokenIssuer($service, new AuthorizationScopeBuilder($this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel ?? fatal_error()), $this->managedObjectContext, new Number($environment[JWTValidityTimeIntervalKey] ?? 1800)->intValue);
            $tokenString = $issuer->issue($user, $this->authenticationStrategy->context);
            $token = $service->decode($tokenString);
            $this->identitySource = new JWTIdentitySource($user, $token);
            $data["token"] = $tokenString;
        } else {
            $session = $this->session;
            $session->regenerateID();
            $session->setValueForKey($user, "user");
            $this->identitySource = new SessionIdentitySource($user, $session);
            $data["session"] = $session->id;
        }
        $this->data = $data;
    }

    #[Action]
    public function logout(): void
    {
        $this->identitySource?->invalidate();
        $this->identitySource = null;
        $this->statusCode = HTTPStatusCode::noContent;
    }
}
