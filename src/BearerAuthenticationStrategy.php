<?php

namespace Sabatier\Service;

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
        get {
            if (!isset($this->credential)) {
                if ($key = ProcessInfo::processInfo()->environment[JWTPrivateKey]) {
                    /** @noinspection PhpUnhandledExceptionInspection */
                    $token = new JSONWebTokenService($key, $this->context->tokenIssuer)->decode($this->context->authorizationHeader->value);
                    if ($username = $token->payload->username) {
                        $this->credential = new URLCredential($username);
                    }
                }
                $this->credential ??= null;
            }
            return $this->credential;
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
