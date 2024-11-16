<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\URLCredential;
use function Sabatier\Foundation\is_password;

class BasicAuthorization extends Authorization
{
    public ?URLCredential $credential {
        get {
            $components = explode(":", base64_decode($this->data));
            if (count($components) !== 2) {
                return null;
            }
            [$username, $password] = $components;
            return new URLCredential($username, $password);
        }
    }
    public bool $isValid {
        get {
            if (!($credential = $this->credential) || !($password = $this->user?->valueForKey("password"))) {
                return false;
            }
            if (is_password($password)) {
                return password_verify((string)$credential->password, $password);
            }
            return $credential->password === $password;
        }
    }
}
