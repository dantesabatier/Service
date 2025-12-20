<?php

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\ProcessInfo;

/** @internal */
final class BearerAuthenticationStrategy extends AuthenticationStrategy
{
    public AuthenticationScheme $scheme {
        get => AuthenticationScheme::bearer;
    }
    private(set) ?URLCredential $credential {
        /**
         * @throws Exception
         */
        get {
            if (isset($this->credential)) {
                return $this->credential;
            }
            if (!($jwtKey = ProcessInfo::processInfo()->environment[JWTPrivateKey])) {
                return $this->credential = null;
            }
            return $this->credential = new BearerCredentialProvider(new JSONWebTokenService($jwtKey, $this->context->tokenIssuer))->decode($this->context);
        }
    }
    public bool $isValid {
        get => $this->credential !== null;
    }

    #[Override]
    public static function isSupported(AuthenticationScheme $scheme): bool
    {
        return $scheme === AuthenticationScheme::bearer;
    }
}
