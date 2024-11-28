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
            $decoder = new JSONWebTokenDecoder($key, $this->host);
            /** @var string|null $username */
            $username = $decoder->decode($this->credentials)->sec;
            if (!$username) {
                return null;
            }
            return new URLCredential($username);
        }
    }
    public bool $isValid {
        get {
            if (!($credential = $this->credential) || !($username = $this->user?->username)) {
                return false;
            }
            return $credential->user === $username;
        }
    }
}
