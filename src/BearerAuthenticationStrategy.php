<?php

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\ProcessInfo;

/** @internal */
final class BearerAuthenticationStrategy extends AuthenticationStrategy
{
    public ?JSONWebToken $token {
        /**
         * @throws Exception
         */
        get {
            if (isset($this->token)) {
                return $this->token;
            }
            if (!($jwtKey = ProcessInfo::processInfo()->environment[JWTPrivateKey])) {
                return $this->token = null;
            }
            return $this->token = new JSONWebTokenService($jwtKey, $this->context->tokenIssuer)->decode($this->context->authorizationHeader->value);
        }
    }
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
            if (!($token = $this->token)) {
                return $this->credential = null;
            }
            if (!($username = $token->payload->sub)) {
                return $this->credential = null;
            }
            return $this->credential = new URLCredential($username);
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
