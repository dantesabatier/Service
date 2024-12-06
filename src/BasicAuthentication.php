<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\URLCredential;
use function Sabatier\Foundation\is_password;

/** @internal */
class BasicAuthentication extends Authentication
{
    public ?URLCredential $credential {
        get {
            $components = explode(":", base64_decode($this->manager->authenticationData));
            if (count($components) !== 2) {
                return null;
            }
            [$username, $password] = $components;
            return new URLCredential($username, $password);
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

    public static function canInit(AuthenticationScheme $scheme): bool
    {
        return $scheme === AuthenticationScheme::basic;
    }
}
