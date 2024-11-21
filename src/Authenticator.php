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

class Authenticator extends Responder
{
    public ArrayClass $allowedMethods {
        get => $this->allowedMethods ??= new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::post]);
    }
    private(set) AuthenticationScheme $scheme;
    private(set) string $data;
    public Authorization $authorization {
        get => $this->authorization ??= match ($this->scheme) {
            AuthenticationScheme::basic => new BasicAuthorization($this->data),
            AuthenticationScheme::bearer => new BearerAuthorization($this->data),
            AuthenticationScheme::digest => new DigestAuthorization($this->data),
        };
    }
    public bool $isProtectedContentAvailable {
        get => $this->isProtectedContentAvailable ??= $this->request->httpMethod === HTTPRequestMethod::options || $this->authorization->isValid;
    }

    public function __construct()
    {
        $value = $this->request->valueForHttpHeaderField("Authorization") ?? "";
        $components = explode(" ", $value, 2);
        if (count($components) !== 2) {
            $components = [AuthenticationScheme::basic->value, ""];
        }
        [$name, $data] = $components;
        $this->scheme = AuthenticationScheme::tryFrom($name) ?? AuthenticationScheme::basic;
        $this->data = $data;
    }

    /**
     * @throws Exception
     */
    #[Action]
    public function login(): void
    {
        $user = $this->authorization->user ?? throw new UnauthorizedException();
        /** @var Dictionary<mixed> $data */
        $data = new Dictionary();
        $data["user"] = $user;
        $username = $user->username;
        if ($key = UserDefaults::standard()->string(JWTPrivateKeyPreferenceKey)) {
            $date = new Date();
            $encoder = new JSONWebTokenEncoder($key);
            $data["token"] = $encoder->encode(new JSONWebToken(iss: $this->request->url->host, exp: $date->addingTimeInterval(UserDefaults::standard()->float(JWTValidityTimeIntervalPreferenceKey))->timeIntervalSinceReferenceDate, nbf: $date->timeIntervalSinceReferenceDate, iat: $date->timeIntervalSinceReferenceDate, jti: base64_encode(read_random(16)), sec: $username));
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
