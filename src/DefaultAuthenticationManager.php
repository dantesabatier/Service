<?php

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\UserDefaults;
use function Sabatier\Foundation\read_random;


/** @internal */
final class DefaultAuthenticationManager extends AuthenticationManager
{
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::post]);
    }
    public Authentication $authentication {
        get {
            if (!isset($this->authentication)) {
                $authenticationClass = AuthenticationFactory::getAuthenticationClass(AuthenticationFactory::getAuthentications() ?? new ArrayClass(), $this->request->authorizationHeader->scheme) ?? throw new UnimplementedException();
                $this->authentication = new $authenticationClass($this->request, $this->managedObjectContext, $this->isFirstResponder ? $this->request->serialization : null, $this->authenticationService);
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
                if (!($user = $this->authentication->authenticatedUser)) {
                    return $this->isProtectedContentAvailable = false;
                }
                $this->isProtectedContentAvailable = $this->authorizationService->isAuthorized($user, $this->request->url->lastPathComponent, match ($this->request->httpMethod) {
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

    /** @throws Exception */
    #[Action]
    #[Override]
    public function login(): void
    {
        $user = $this->authentication->authenticatedUser ?? throw new UnauthorizedException();
        /** @var Dictionary<mixed> $data */
        $data = new Dictionary();
        $data["user"] = $user;
        $username = $user->username;
        if ($key = UserDefaults::standard()->string(JWTPrivateKeyPreferenceKey)) {
            $date = new Date();
            $payloadRawValue = [JWTIssuerKey => $this->request->url->host, JWTExpirationTimeKey => $date->addingTimeInterval(UserDefaults::standard()->float(JWTValidityTimeIntervalPreferenceKey))->timeIntervalSinceReferenceDate, JWTNotBeforeTimeKey => $date->timeIntervalSinceReferenceDate, JWTIssuedAtTimeKey => $date->timeIntervalSinceReferenceDate, JWTIdKey => base64_encode(read_random(16)), JWTUsernameKey => $username];
            $data["token"] = new JSONWebTokenService($key)->encode($payloadRawValue);
        }
        $session = Application::shared()->session;
        $session->regenerateID();
        $session->setValueForKey($username, "username");
        $this->content = json_encode($data, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        $this->headerFields["Content-Type"] = "application/json";
    }

    #[Action]
    #[Override]
    public function logout(): void
    {
        $session = Application::shared()->session;
        $session->setValueForKey(null, "username");
        $this->statusCode = HTTPStatusCode::noContent;
    }
}
