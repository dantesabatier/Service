<?php

namespace Sabatier\Service;

use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Networking\URLCredential;
use function Sabatier\Foundation\is_password;
use function Sabatier\Foundation\string_is_equal;

/** @internal */
class BasicAuthentication extends Authentication
{
    public AuthenticationScheme $scheme {
        get => AuthenticationScheme::basic;
    }
    private(set) ?URLCredential $credential {
        get {
            if (!isset($this->credential)) {
                $components = explode(":", base64_decode($this->request->authParameter->value));
                if (count($components) === 2) {
                    [$username, $password] = $components;
                    $this->credential = new URLCredential($username, $password);
                }
                $this->credential ??= null;
            }
            return $this->credential;
        }
    }
    public bool $isValid {
        get {
            if (!($credential = $this->credential) || !($password = $this->user?->password)) {
                return false;
            }
            if (is_password($password)) {
                return password_verify((string)$credential->password, $password);
            }
            return $credential->password === $password;
        }
    }

    public static function canInit(Request $request): bool
    {
        return string_is_equal($request->authParameter->name, AuthenticationScheme::basic->value, CompareOptions::caseInsensitive);
    }
}
