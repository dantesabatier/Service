<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\UserDefaults;
use function Sabatier\Foundation\read_random;
use function Sabatier\Foundation\string_is_equal;

class AccessManager extends Responder
{
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::post]);
    }
    private(set) AuthenticationScheme $scheme;
    /** @internal */
    private(set) string $authenticationData;
    private(set) Authentication $authentication {
        get => $this->authentication ??= new ($this->authenticationClass)($this);
    }
    public bool $isProtectedContentAvailable {
        get => $this->isProtectedContentAvailable ??= $this->isProtectedContentAvailable();
    }
    /** @var class-string<Authentication> */
    public string $authenticationClass {
        get {
            $authenticationClasses = Authentication::getAuthentications() ?? new ArrayClass();
            return Authentication::getAuthenticationClass($authenticationClasses, $this->scheme) ?? BasicAuthentication::class;
        }
    }

    public function __construct()
    {
        $value = $this->request->valueForHttpHeaderField("Authorization") ?? "";
        $components = explode(" ", $value, 2);
        if (count($components) !== 2) {
            $components = [AuthenticationScheme::basic->value, ""];
        }
        [$scheme, $data] = $components;
        $this->scheme = AuthenticationScheme::tryFrom($scheme) ?? AuthenticationScheme::basic;
        $this->authenticationData = $data;
        self::registerAuthentications();
    }

    private static function registerAuthentications(): void
    {
        Authentication::registerClass(BasicAuthentication::class);
        Authentication::registerClass(BearerAuthentication::class);
        Authentication::registerClass(DigestAuthentication::class);
    }

    private function isProtectedContentAvailable(): bool
    {
        if ($this->request->httpMethod === HTTPRequestMethod::options) {
            return true;
        }
        $endpoint = $this->request->url->lastPathComponent;
        if (string_is_equal($endpoint, (string)$this->selector, CompareOptions::caseInsensitive)) {
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
        if ($key = UserDefaults::standard()->string(JWTPrivateKeyPreferenceKey)) {
            $date = new Date();
            $encoder = new JSONWebTokenEncoder($key);
            $data["token"] = $encoder->encode(new JSONWebToken(iss: $this->request->url->host, exp: $date->addingTimeInterval(UserDefaults::standard()->float(JWTValidityTimeIntervalPreferenceKey))->timeIntervalSinceReferenceDate, nbf: $date->timeIntervalSinceReferenceDate, iat: $date->timeIntervalSinceReferenceDate, jti: base64_encode(read_random(16)), username: $username));
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
