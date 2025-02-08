<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\URLCredential;
use function Sabatier\Foundation\is_password;

/** @internal */
class BasicAuthentication extends Authentication
{
    public AuthenticationScheme $scheme {
        get => AuthenticationScheme::basic;
    }
    private(set) ?URLCredential $credential {
        get {
            if (!isset($this->credential)) {
                $components = explode(":", base64_decode((string)$this->request->authenticationData));
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
        return $request->authenticationScheme === AuthenticationScheme::basic;
    }
}
