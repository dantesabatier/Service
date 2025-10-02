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
class AuthenticationManager extends Responder
{
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::post]);
    }
    public AuthenticationService $service {
        get => $this->service ??= new DefaultAuthenticationService($this);
    }
    public Authentication $authentication {
        get => $this->service->authentication;
    }
    public bool $isProtectedContentAvailable {
        get => $this->service->isProtectedContentAvailable;
    }

    public function __construct()
    {
        self::initialize();
    }

    #[Override]
    public static function initialize(): void
    {
        ApplicationSecurityBootstrap::boot();
    }

    /** @throws Exception */
    #[Action]
    public function login(): void
    {
        $user = $this->authentication->user ?? throw new UnauthorizedException();
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
    public function logout(): void
    {
        $session = Application::shared()->session;
        $session->setValueForKey(null, "username");
        $this->statusCode = HTTPStatusCode::noContent;
    }
}
