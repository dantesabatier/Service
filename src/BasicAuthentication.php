<?php

namespace Sabatier\Service;

use Override;
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
                $components = explode(BasicAuthenticationComponentDelimiter, base64_decode($this->request->authorizationHeader->value));
                if (count($components) === BasicAuthenticationComponentCount) {
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
            return hash_equals($password, (string)$credential->password);
        }
    }

    #[Override]
    public static function isSupported(AuthenticationScheme $scheme): bool
    {
        return $scheme === AuthenticationScheme::basic;
    }
}
