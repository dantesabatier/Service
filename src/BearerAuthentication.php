<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\URLCredential;

/** @internal */
final class BearerAuthentication extends Authentication
{
    #[Override]
    public AuthenticationScheme $scheme {
        get => AuthenticationScheme::bearer;
    }
    private bool $isCredentialResolved = false;
    #[Override]
    private(set) ?URLCredential $credential {
        get {
            if ($this->isCredentialResolved) {
                return $this->credential;
            }
            $this->isCredentialResolved = true;
            if (!($username = $this->token?->payload?->subject)) {
                return $this->credential = null;
            }
            return $this->credential = new URLCredential($username);
        }
    }
    #[Override]
    private(set) bool $isValid {
        get => $this->isValid ??= $this->credential !== null;
    }
    private bool $isTokenResolved = false;
    private(set) ?JSONWebToken $token {
        /**
         * @throws Exception
         */
        get {
            if ($this->isTokenResolved) {
                return $this->token;
            }
            $this->isTokenResolved = true;
            if (!($jwtKey = $this->environment[JWTPrivateKey])) {
                return $this->token = null;
            }
            return $this->token = new JSONWebTokenService($jwtKey)->decode($this->context->authorizationHeader->value);
        }
    }
    /** @var ArrayClass<string> */
    #[Override]
    protected(set) ArrayClass $technicalScopes {
        get => $this->technicalScopes ??= new ArrayClass($this->token?->payload?->technicalScopes ?? []);
    }
    /** @var ArrayClass<string> */
    #[Override]
    protected(set) ArrayClass $authorizationScopes {
        get => $this->authorizationScopes ??= new ArrayClass($this->token?->payload?->authorizationScopes ?? []);
    }

    #[Override]
    public static function isSupported(AuthenticationScheme $scheme): bool
    {
        return $scheme === AuthenticationScheme::bearer;
    }
}
