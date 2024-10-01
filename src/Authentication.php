<?php

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\UserDefaults;
use function Sabatier\Foundation\read_random;

class Authentication extends Responder
{
    public readonly Authorization $authorization;
    public readonly AuthenticationScheme $scheme;

    public function __construct()
    {
        parent::__construct();
        unset($this->authorization);
        unset($this->scheme);
        unset($this->isProtectedContentAvailable);
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function __get(string $name)
    {
        if ($name === "authorization") {
            $this->$name = new Authorization(new AuthorizationDescription($this->request));
            return $this->$name;
        }
        if ($name === "scheme") {
            $this->$name = $this->authorization->authenticationScheme;
            return $this->$name;
        }
        if ($name === "isProtectedContentAvailable") {
            $this->$name = $this->request->httpMethod === HTTPRequestMethod::options || Application::shared()->session->valueForKey("user") || $this->authorization->isValid;
            return $this->$name;
        }
        if ($name === "allowedMethods") {
            $this->$name = new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::post]);
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
        $this->isProtectedContentAvailable ?: throw new UnauthorizedException();
        $date = new Date();
        $user = $this->authorization->user;
        /** @var Dictionary<mixed> $data */
        $data = new Dictionary();
        $data["user"] = $user;
        $username = $user?->valueForKey("username");
        if ($key = UserDefaults::standard()->string(JWTPrivateKeyPreferenceKey)) {
            $encoder = new JWTEncoder($key);
            $token = $encoder->encode([JWTIssuedField => $date->timeIntervalSinceReferenceDate, JWTUniqueIDField => base64_encode(read_random(16)), JWTIssuerField => $this->request->url->host, JWTNotBeforeField => $date->timeIntervalSinceReferenceDate, JWTExpirationField => $date->addingTimeInterval(UserDefaults::standard()->float(JWTValidityTimeIntervalPreferenceKey))->timeIntervalSinceReferenceDate, JWTDataField => $username]);
            $data["token"] = $token;
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
    }
}
