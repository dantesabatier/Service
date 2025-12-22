<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Number;
use function Sabatier\Foundation\fatal_error;

/**
 * An object that manages authentication processes.
 */
class AuthenticationManager extends Responder
{
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::post]);
    }
    private AuthenticationStrategy $authenticationStrategy {
        get {
            if (!isset($this->authenticationStrategy)) {
                $authenticationStrategyClass = AuthenticationStrategyFactory::getAuthenticationStrategyClass(AuthenticationStrategyFactory::getAuthenticationStrategies() ?? new ArrayClass(), $this->request->authorizationHeader->scheme) ?? throw new UnimplementedException();
                $this->authenticationStrategy = new $authenticationStrategyClass(new AuthenticationContext($this->request->authorizationHeader, $this->request->url->host, $this->request->httpMethod, $this->managedObjectContext, $this->isFirstResponder ? $this->request->serialization : null, $this->authenticationService), $this->environment);
            }
            return $this->authenticationStrategy;
        }
    }
    /** @var Authentication The authentication object managing the authentication process. */
    private(set) Authentication $authentication {
        get => $this->authentication ??= new Authentication($this->authenticationStrategy);
    }
    public AccessEvaluatorChain $accessEvaluatorChain {
        get => $this->accessEvaluatorChain ??= new AccessEvaluatorChain(new ArrayClass([new SessionAuthenticationEvaluator(), new AuthenticationEvaluator(), new JWTScopeEvaluator(), new AuthorizationEvaluator()]));
    }
    public bool $isProtectedContentAvailable {
        /**
         * @throws Exception
         */
        get => $this->isProtectedContentAvailable ??= $this->accessEvaluatorChain->evaluate($this->request, $this->authentication, $this->session, $this->environment, $this->authorizationService, $this->managedObjectContext);
    }

    public function __construct()
    {
        ApplicationSecurityBootstrap::boot();
    }

    /**
     * Authenticates the current request and establishes an authenticated identity.
     *
     * @throws Exception
     */
    #[Action(decorators: [JSONDecorator::class])]
    public function login(): void
    {
        $user = $this->authentication->authenticatedUser ?? throw new UnauthorizedException();
        /** @var Dictionary<mixed> $data */
        $data = new Dictionary();
        $data[AuthenticationUserKey] = $user;
        $environment = $this->environment;
        if ($jwtKey = $environment[JWTPrivateKey]) {
            $data[AuthenticationTokenKey] = new JSONWebTokenIssuer(new JSONWebTokenService($jwtKey, $this->request->url->host), new AuthorizationScopeBuilder($this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel ?? fatal_error()), $this->managedObjectContext, new Number($environment[JWTValidityTimeIntervalKey] ?? 1800)->intValue)->issue($user, $this->authenticationStrategy->context, new ArrayClass([AuthenticationScopeAccess, AuthenticationScopeRefresh]));
        } else {
            $session = $this->session;
            $session->regenerateID();
            $session->setValueForKey($user, SessionUserKey);
            $session->setValueForKey(true, SessionAuthenticatedKey);
        }
        $this->data = $data;
    }

    /**
     * Invalidates the current authenticated identity.
     */
    #[Action]
    public function logout(): void
    {
        $this->session->invalidate();
        $this->statusCode = HTTPStatusCode::noContent;
    }

    /**
     * @throws Exception
     * @internal
     */
    public function refresh(): Dictionary
    {
        $strategy = $this->authenticationStrategy;
        $strategy->isValid ?: throw new UnauthorizedException();
        $user = $strategy->authenticatedUser ?? throw new UnauthorizedException();
        $environment = $this->environment;
        $jwtKey = $environment[JWTPrivateKey] ?? throw new UnauthorizedException();
        $data = new Dictionary();
        $data[AuthenticationUserKey] = $user;
        $data[AuthenticationTokenKey] = new JSONWebTokenIssuer(new JSONWebTokenService($jwtKey, $this->request->url->host), new AuthorizationScopeBuilder($this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel ?? fatal_error()), $this->managedObjectContext, new Number($environment[JWTValidityTimeIntervalKey] ?? 1800)->intValue)->issue($user, $strategy->context, new ArrayClass([AuthenticationScopeAccess]));
        return $data;
    }
}
