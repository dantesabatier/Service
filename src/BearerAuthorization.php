<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\UserDefaults;

class BearerAuthorization extends Authorization
{
    public ?URLCredential $credential {
        get {
            if (!($key = UserDefaults::standard()->string(JWTPrivateKeyPreferenceKey))) {
                return null;
            }
            $decoder = new JSONWebTokenDecoder($key, Application::shared()->request->url->host);
            /** @var string|null $username */
            $username = $decoder->decode($this->data)->sec;
            if (!$username) {
                return null;
            }
            return new URLCredential($username);
        }
    }
    public bool $isValid {
        get {
            if (!($credential = $this->credential) || !($username = $this->user?->valueForKey("username"))) {
                return false;
            }
            return $credential->user === $username;
        }
    }
}
