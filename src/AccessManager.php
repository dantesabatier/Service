<?php

namespace Sabatier\Service;

use Exception;
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
        self::registerAuthentications();
        self::registerJSONWebTokenCodingStrategies();
    }

    private static function registerAuthentications(): void
    {
        Authentication::registerClass(BasicAuthentication::class);
        Authentication::registerClass(BearerAuthentication::class);
        Authentication::registerClass(DigestAuthentication::class);
    }

    private static function registerJSONWebTokenCodingStrategies(): void
    {
        JSONWebTokenCoderStrategyFactory::shared()->register(JSONWebTokenHS256EncoderStrategy::class);
        JSONWebTokenCoderStrategyFactory::shared()->register(JSONWebTokenHS256DecoderStrategy::class);
    }

    private function resolveAuthentication(): Authentication
    {
        $authenticationClass = Authentication::getAuthenticationClass(Authentication::getAuthentications() ?? new ArrayClass(), $this->request) ?? throw new UnimplementedException();
        return new $authenticationClass($this->request, $this->managedObjectContext, $this->isFirstResponder ? $this->request->serialization : null);
    }

    private function isRequestAuthorized(): bool
    {
        if ($this->request->httpMethod === HTTPRequestMethod::options) {
            return true;
        }
        if (!$this->authentication->isValid) {
            return false;
        }
        if (!($authorization = $this->authentication->user?->authorization)) {
            return false;
        }
        return match ($this->request->httpMethod) {
            HTTPRequestMethod::get, HTTPRequestMethod::head => $authorization->type === AuthorizationType::read,
            HTTPRequestMethod::post => $authorization->type === AuthorizationType::create,
            HTTPRequestMethod::patch, HTTPRequestMethod::put => $authorization->type === AuthorizationType::update,
            HTTPRequestMethod::delete => $authorization->type === AuthorizationType::delete,
            default => false
        };
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
        if (($key = UserDefaults::standard()->string(JWTPrivateKeyPreferenceKey)) && ($JSONWebTokenEncoderStrategyClass = JSONWebTokenCoderStrategyFactory::shared()->getStrategyClass(JSONWebTokenCoderStrategyFactory::shared()->encoderStrategies, JSONWebTokenSigningAlgorithm::tryFrom((string)UserDefaults::standard()->string(JWTSignatureAlgorithmPreferenceKey)) ?? JSONWebTokenSigningAlgorithm::hs256))) {
            $date = new Date();
            $token = new JSONWebToken(iss: $this->request->url->host, exp: $date->addingTimeInterval(UserDefaults::standard()->float(JWTValidityTimeIntervalPreferenceKey))->timeIntervalSinceReferenceDate, nbf: $date->timeIntervalSinceReferenceDate, iat: $date->timeIntervalSinceReferenceDate, jti: base64_encode(read_random(16)), username: $username);
            $encoded = new JSONWebTokenEncoder(new $JSONWebTokenEncoderStrategyClass($key))->encode($token);
            $data["token"] = $encoded;
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
