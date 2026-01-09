<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLCredential;

/** @internal */
final class BasicAuthentication extends Authentication
{
    public AuthenticationScheme $scheme {
        get => AuthenticationScheme::basic;
    }
    private(set) ?URLCredential $credential = null;
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

    public function __construct(AuthenticationContext $context, Dictionary $environment)
    {
        parent::__construct($context, $environment);
        $components = explode(BasicAuthenticationComponentDelimiter, base64_decode($this->context->authorizationHeader->value));
        if (count($components) === BasicAuthenticationComponentCount) {
            [$username, $password] = $components;
            $this->credential = new URLCredential($username, $password);
        }
    }

    #[Override]
    public static function isSupported(AuthenticationScheme $scheme): bool
    {
        return $scheme === AuthenticationScheme::basic;
    }
}
