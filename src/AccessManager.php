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
class AccessManager extends Responder
{
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::post]);
    }
    private(set) Authentication $authentication {
        get => $this->authentication ??= $this->resolveAuthentication();
    }
    public bool $isProtectedContentAvailable {
        get => $this->isProtectedContentAvailable ??= $this->isRequestAuthorized();
    }

    public function __construct()
    {
        self::initialize();
    }

    #[Override]
    public static function initialize(): void
    {
        self::registerAuthentications();
        self::registerJSONWebTokenCodingStrategies();
    }

    private static function registerAuthentications(): void
    {
        $classes = [BasicAuthentication::class, BearerAuthentication::class, DigestAuthentication::class];
        foreach ($classes as $class) {
            AuthenticationFactory::registerClass($class);
        }
    }

    private static function registerJSONWebTokenCodingStrategies(): void
    {
        $classes = [JSONWebTokenHS256EncoderStrategy::class, JSONWebTokenHS256DecoderStrategy::class, JSONWebTokenRS256EncoderStrategy::class, JSONWebTokenRS256DecoderStrategy::class];
        foreach ($classes as $class) {
            JSONWebTokenCoderStrategyFactory::shared()->register($class);
        }
    }

    private function resolveAuthentication(): Authentication
    {
        $authenticationClass = AuthenticationFactory::getAuthenticationClass(AuthenticationFactory::getAuthentications() ?? new ArrayClass(), $this->request->authorizationHeader->scheme) ?? throw new UnimplementedException();
        return new $authenticationClass($this->request, $this->managedObjectContext, $this->isFirstResponder ? $this->request->serialization : null);
    }

    private function isRequestAuthorized(): bool
    {
        if ($this->request->isPreflight) {
            return true;
        }
        if (!$this->authentication->isValid) {
            return false;
        }
        return $this->authentication->user?->authorization instanceof Authorization;
    }

    /**
     * @throws Exception
     */
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
            $payloadRawValue = ["iss" => $this->request->url->host, "exp" => $date->addingTimeInterval(UserDefaults::standard()->float(JWTValidityTimeIntervalPreferenceKey))->timeIntervalSinceReferenceDate, "nbf" => $date->timeIntervalSinceReferenceDate, "iat" => $date->timeIntervalSinceReferenceDate, "jti" => base64_encode(read_random(16)), "username" => $username];
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
