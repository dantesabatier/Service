<?php

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Number;
use function Sabatier\Foundation\fatal_error;

/**
 * An object that manages authentication processes.
 */
final class AuthenticationManager extends Responder
{
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::post]);
    }
    /** @var Authentication The authentication object managing the authentication process. */
    private(set) Authentication $authentication {
        get => $this->authentication ??= new AuthenticationProvider($this->request->authorizationHeader->scheme, new AuthenticationContext($this->request->authorizationHeader, $this->request->url->host, $this->request->httpMethod, $this->managedObjectContext, $this->isFirstResponder ? $this->request->serialization : null, $this->authenticationService), $this->environment)->authentication;
    }
    /** @var AccessEvaluator The access evaluator responsible for determining if a request has permission to access a protected resource. */
    public AccessEvaluator $accessEvaluator {
        get => $this->accessEvaluator ??= $this->selector === AuthenticationRefreshSelector ? new AccessEvaluatorChain(new ArrayClass([new AuthenticationEvaluator(), new JSONWebTokenScopeEvaluator(AuthenticationScopeRefresh), new JSONWebTokenRefreshTimeEvaluator(), new JSONWebTokenEnabledEvaluator(), new JSONWebTokenVersionEvaluator()])) : new AccessEvaluatorChain(new ArrayClass([new SessionAuthenticationEvaluator(), new AuthenticationEvaluator(), new JSONWebTokenScopeEvaluator(AuthenticationScopeAccess), new JSONWebTokenAccessTimeEvaluator(), new JSONWebTokenEnabledEvaluator(), new JSONWebTokenVersionEvaluator(), new AuthorizationEvaluator()]));
    }
    /** @var bool Indicates whether the current request can access protected content. Determined by evaluating the configured access evaluator chain. */
    #[Override]
    public bool $isProtectedContentAvailable {
        /**
         * @throws Exception
         */
        get => $this->isProtectedContentAvailable ??= $this->accessEvaluator->evaluate(new AccessEvaluationContext($this->request, $this->authentication, $this->session, $this->environment, $this->authorizationService, $this->managedObjectContext));
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
    #[Action(transformers: [JSONTransformer::class, NoCacheHeaderTransformer::class])]
    public function login(): void
    {
        $user = $this->authentication->authenticatedUser ?? throw new UnauthorizedException();
        /** @var Dictionary<mixed> $data */
        $data = new Dictionary();
        $data[AuthenticationUserKey] = $user;
        $environment = $this->environment;
        if ($privateKey = $environment[JWTPrivateKey]) {
            $data[AuthenticationTokenKey] = new JSONWebTokenIssuer(new JSONWebTokenService($privateKey, $this->request->url->host), new AuthorizationScopeBuilder($this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel ?? fatal_error()), $this->managedObjectContext, new Number($environment[JWTValidityTimeIntervalKey] ?? JWTValidityDefaultTimeInterval)->floatValue)->issue($user, $this->authentication->context, new ArrayClass([AuthenticationScopeAccess, AuthenticationScopeRefresh]));
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
     * @throws Exception
     */
    #[Action(transformers: [NoCacheHeaderTransformer::class])]
    public function logout(): void
    {
        if ($this->isSessionEnabled) {
            $this->session->invalidate();
            return;
        }
        $user = $this->authentication->authenticatedUser ?? throw new UnauthorizedException();
        $user->refreshTokenVersion += 1;
        $this->managedObjectContext->save();
        $this->statusCode = HTTPStatusCode::noContent;
    }

    /**
     * Issues a new JSON Web Token for an already authenticated JWT identity.
     *
     * @throws Exception
     */
    #[Action(transformers: [JSONTransformer::class, NoCacheHeaderTransformer::class])]
    public function refresh(): void
    {
        $authentication = $this->authentication;
        $authentication->isValid ?: throw new UnauthorizedException();
        $user = $authentication->authenticatedUser ?? throw new UnauthorizedException();
        $environment = $this->environment;
        $privateKey = $environment[JWTPrivateKey] ?? throw new UnauthorizedException();
        /** @var Dictionary<mixed> $data */
        $data = new Dictionary();
        $data[AuthenticationUserKey] = $user;
        $data[AuthenticationTokenKey] = new JSONWebTokenIssuer(new JSONWebTokenService($privateKey, $this->request->url->host), new AuthorizationScopeBuilder($this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel ?? fatal_error()), $this->managedObjectContext, new Number($environment[JWTValidityTimeIntervalKey] ?? JWTValidityDefaultTimeInterval)->floatValue)->issue($user, $authentication->context, new ArrayClass([AuthenticationScopeAccess, AuthenticationScopeRefresh]));
        $this->data = $data;
    }
}
