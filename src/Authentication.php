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

class Authentication extends Responder
{
    public readonly AuthenticationScheme $scheme;
    public readonly Authorization $authorization;

    public function __construct()
    {
        parent::__construct();
        unset($this->authorization);
        unset($this->scheme);
        unset($this->isProtectedContentAvailable);
        $this->allowedMethods = new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::post]);
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function __get(string $name)
    {
        if ($name === "authorization") {
            $this->$name = new Authorization($this->request->valueForHttpHeaderField("Authorization") ?? "");
            return $this->$name;
        }
        if ($name === "scheme") {
            $this->$name = $this->authorization->scheme;
            return $this->$name;
        }
        if ($name === "isProtectedContentAvailable") {
            $this->$name = $this->request->httpMethod === HTTPRequestMethod::options || Application::shared()->session->valueForKey("user") !== null || $this->authorization->isValid;
            return $this->$name;
        }
        return parent::__get($name);
    }

    /**
     * @throws Exception
     */
    #[Action]
    public function login(): void
    {
        $user = $this->authorization->user;
        /** @var Dictionary<mixed> $data */
        $data = new Dictionary();
        $data["user"] = $user;
        $username = $user?->valueForKey("username");
        if ($key = UserDefaults::standard()->string(JWTPrivateKeyPreferenceKey)) {
            $date = new Date();
            $encoder = new JSONWebTokenEncoder($key);
            $data["token"] = $encoder->encode(new JSONWebToken(iss: $this->request->url->host, exp: $date->addingTimeInterval(UserDefaults::standard()->float(JWTValidityTimeIntervalPreferenceKey))->timeIntervalSinceReferenceDate, nbf: $date->timeIntervalSinceReferenceDate, iat: $date->timeIntervalSinceReferenceDate, jti: base64_encode(read_random(16)), sec: $username));
        }
        $session = Application::shared()->session;
        $session->regenerateID();
        $session->setValueForKey($username, "user");
        $this->content = json_encode($data, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        $this->contentType = "application/json";
    }

    #[Action]
    public function logout(): void
    {
        $session = Application::shared()->session;
        $session->setValueForKey(null, "user");
        $this->statusCode = HTTPStatusCode::noContent;
    }
}
