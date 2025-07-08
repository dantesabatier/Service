<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\UserDefaults;

/** @internal */
class BearerAuthentication extends Authentication
{
    public AuthenticationScheme $scheme {
        get => AuthenticationScheme::bearer;
    }
    private(set) ?URLCredential $credential {
        get {
            if (!isset($this->credential)) {
                if ($key = UserDefaults::standard()->string(JWTPrivateKeyPreferenceKey)) {
                    $data = $this->request->authorizationHeader->value;
                    $token = new JSONWebTokenService($key, $this->request->url->host)->decode($data);
                    if ($username = $token->payload->username) {
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

    public static function isSupported(AuthenticationScheme $scheme): bool
    {
        return $scheme === AuthenticationScheme::bearer;
    }
}
