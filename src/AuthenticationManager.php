<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\ProcessInfo;
use function Sabatier\Foundation\read_random;

/**
 * An object that manages authentication processes.
 */
class AuthenticationManager extends Responder
{
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::post]);
    }
    /** @var Authentication The authentication object managing the authentication process. */
    public Authentication $authentication {
        get {
            if (!isset($this->authentication)) {
                $authenticationStrategyClass = AuthenticationStrategyFactory::getAuthenticationStrategyClass(AuthenticationStrategyFactory::getAuthenticationStrategies() ?? new ArrayClass(), $this->request->authorizationHeader->scheme) ?? throw new UnimplementedException();
                $this->authentication = new Authentication(new $authenticationStrategyClass(new AuthenticationContext($this->request->authorizationHeader, $this->request->url->host, $this->request->httpMethod, $this->managedObjectContext, $this->isFirstResponder ? $this->request->serialization : null, $this->authenticationService)));
            }
            return $this->authentication;
        }
    }
    public bool $isProtectedContentAvailable {
        /**
         * @throws Exception
         */
        get {
            if (!isset($this->isProtectedContentAvailable)) {
                if ($this->request->isPreflight) {
                    return $this->isProtectedContentAvailable = true;
                }
                if (!$this->authentication->isValid) {
                    return $this->isProtectedContentAvailable = false;
                }
                if (!($authenticatedUser = $this->authentication->authenticatedUser)) {
                    return $this->isProtectedContentAvailable = false;
                }
                $this->isProtectedContentAvailable = $this->authorizationService->isAuthorized($authenticatedUser, $this->request->url->lastPathComponent, match ($this->request->httpMethod) {
                    HTTPRequestMethod::head, HTTPRequestMethod::get => AuthorizationType::read,
                    HTTPRequestMethod::post => AuthorizationType::create,
                    HTTPRequestMethod::put, HTTPRequestMethod::patch => AuthorizationType::update,
                    HTTPRequestMethod::delete => AuthorizationType::delete,
                    default => throw new MethodNotAllowedException()
                }, $this->managedObjectContext);
            }
            return $this->isProtectedContentAvailable;
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
            $date = new Date();
            $payloadRawValue = [JWTIssuerKey => $this->request->url->host, JWTExpirationTimeKey => $date->addingTimeInterval($environment[JWTValidityTimeIntervalKey] ?? 0)->timeIntervalSinceReferenceDate, JWTNotBeforeTimeKey => $date->timeIntervalSinceReferenceDate, JWTIssuedAtTimeKey => $date->timeIntervalSinceReferenceDate, JWTIdKey => base64_encode(read_random(16)), JWTUsernameKey => $username];
            $data["token"] = new JSONWebTokenService($jwtKey)->encode($payloadRawValue);
        } else {
            $session = $this->session;
            $session->setValueForKey($username, "authenticatedUser");
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
