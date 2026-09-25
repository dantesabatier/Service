<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Networking\URLCredential;

/** @internal */
final class BasicAuthentication extends Authentication
{
    #[Override]
    public AuthenticationScheme $scheme {
        get => AuthenticationScheme::basic;
    }
    private bool $isCredentialResolved = false;
    #[Override]
    private(set) ?URLCredential $credential {
        get {
            if ($this->isCredentialResolved) {
                return $this->credential;
            }
            $this->isCredentialResolved = true;
            $decoded = base64_decode($this->context->authorizationHeader->value);
            if (!mb_check_encoding($decoded, "UTF-8")) {
                return $this->credential = null;
            }
            $components = explode(BasicAuthenticationComponentDelimiter, $decoded);
            if (count($components) !== BasicAuthenticationComponentCount) {
                return $this->credential = null;
            }
            [$username, $password] = $components;
            return $this->credential = new URLCredential($username, $password);
        }
    }
    #[Override]
    private(set) bool $isValid {
        get {
            if (isset($this->isValid)) {
                return $this->isValid;
            }
            if (!($credential = $this->credential) || !($password = $this->authenticatedUser?->password)) {
                return $this->isValid = false;
            }
            return $this->isValid = password_verify((string)$credential->password, $password);
        }
    }

    #[Override]
    public static function isSupported(AuthenticationScheme $scheme): bool
    {
        return $scheme === AuthenticationScheme::basic;
    }
}
