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
    private(set) string $data;
    private(set) Authentication $authentication;
    public bool $isProtectedContentAvailable {
        get => $this->isProtectedContentAvailable ??= $this->isProtectedContentAvailable();
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
        $this->data = $data;
        $this->authentication = match ($this->scheme) {
            AuthenticationScheme::basic => new BasicAuthentication($this),
            AuthenticationScheme::bearer => new BearerAuthentication($this),
            AuthenticationScheme::digest => new DigestAuthentication($this)
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
        if (!($authorizations = $this->authentication->user?->authorizations)) {
            return false;
        }
        return match ($this->request->httpMethod) {
            HTTPRequestMethod::get, HTTPRequestMethod::head => $authorizations->contains(fn(Authorization $authorization): bool => $authorization->type === AuthorizationType::read),
            HTTPRequestMethod::post, HTTPRequestMethod::patch, HTTPRequestMethod::put => $authorizations->contains(fn(Authorization $authorization): bool => $authorization->type === AuthorizationType::write),
            HTTPRequestMethod::delete => $authorizations->contains(fn(Authorization $authorization): bool => $authorization->type === AuthorizationType::delete),
            default => false
        };
    }
}
