<?php

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\URLCredential;

/** @internal */
final class BearerAuthentication extends Authentication
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
    private(set) ?JSONWebToken $token {
        /**
         * @throws Exception
         */
        get {
            if (isset($this->token)) {
                return $this->token;
            }
            if (!($jwtKey = $this->environment[JWTPrivateKey])) {
                return $this->token = null;
            }
            return $this->token = new JSONWebTokenService($jwtKey, $this->context->tokenIssuer)->decode($this->context->authorizationHeader->value);
        }
    }
    /** @var ArrayClass<string> */
    protected(set) ArrayClass $technicalScopes {
        get => $this->technicalScopes ??= new ArrayClass($this->token?->payload?->scp ?? []);
    }
    /** @var ArrayClass<string> */
    protected(set) ArrayClass $authorizationScopes {
        get => $this->authorizationScopes ??= new ArrayClass($this->token?->payload?->authz ?? []);
    }

    #[Override]
    public static function isSupported(AuthenticationScheme $scheme): bool
    {
        return $scheme === AuthenticationScheme::bearer;
    }
}
