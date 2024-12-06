<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\UserDefaults;

/** @internal */
class BearerAuthentication extends Authentication
{
    private(set) ?URLCredential $credential {
        get {
            if (!isset($this->credential)) {
                if ($key = UserDefaults::standard()->string(JWTPrivateKeyPreferenceKey)) {
                    $decoder = new JSONWebTokenDecoder($key, $this->manager->request->url->host);
                    $username = $decoder->decode($this->manager->authenticationData)->username;
                    if ($username) {
                        $this->credential = new URLCredential($username);
                    }
                }
                $this->credential ??= null;
            }
            return $this->credential;
        }
    }
    public bool $isValid {
        get => $this->credential instanceof URLCredential;
    }

    public static function canInit(AuthenticationScheme $scheme): bool
    {
        return $scheme === AuthenticationScheme::bearer;
    }
}
