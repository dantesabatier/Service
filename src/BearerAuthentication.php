<?php

namespace Sabatier\Service;

use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\UserDefaults;
use function Sabatier\Foundation\string_is_equal;

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
                    $decoder = new JSONWebTokenDecoder($key, $this->request->url->host);
                    $token = $decoder->decode($this->request->authorizationParameter->value);
                    if ($username = $token->username) {
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

    public static function canInit(Request $request): bool
    {
        return string_is_equal($request->authorizationParameter->name, AuthenticationScheme::bearer->value, CompareOptions::caseInsensitive);
    }
}
