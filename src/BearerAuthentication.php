<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\UserDefaults;

/** @internal */
class BearerAuthentication extends Authentication
{
    public ?URLCredential $credential {
        get {
            if (!($key = UserDefaults::standard()->string(JWTPrivateKeyPreferenceKey))) {
                return null;
            }
            $decoder = new JSONWebTokenDecoder($key, $this->request->url->host);
            $username = $decoder->decode($this->data)->username;
            if ($username === null) {
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
