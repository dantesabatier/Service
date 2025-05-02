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
                if (($key = UserDefaults::standard()->string(JWTPrivateKeyPreferenceKey)) && ($username = new JSONWebTokenDecoder(new JSONWebTokenHS256DecoderStrategy($key, $this->request->url->host))->decode($this->request->authParameter->value)->username)) {
                    $this->credential = new URLCredential($username);
                }
                $this->credential ??= null;
            }
            return $this->credential;
        }
    }
    public bool $isValid {
        get => $this->credential instanceof URLCredential;
    }

    public static function canInit(Request $request): bool
    {
        return $request->authParameter->scheme === AuthenticationScheme::bearer;
    }
}
