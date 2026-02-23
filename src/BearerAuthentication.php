<?php

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLCredential;

/** @internal */
final class BearerAuthentication extends Authentication
{
    #[Override]
    public AuthenticationScheme $scheme {
        get => AuthenticationScheme::bearer;
    }
    #[Override]
    private(set) ?URLCredential $credential = null;
    #[Override]
    private(set) bool $isValid {
        get => $this->isValid ??= $this->credential !== null;
    }
    private(set) ?JSONWebToken $token = null;
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

    /**
     * @throws Exception
     */
    public function __construct(AuthenticationContext $context, Dictionary $environment)
    {
        parent::__construct($context, $environment);
        if ($jwtKey = $this->environment[JWTPrivateKey]) {
            $this->token = new JSONWebTokenService($jwtKey, $this->context->tokenIssuer)->decode($this->context->authorizationHeader->value);
        }
        if ($username = $this->token?->payload?->subject) {
            $this->credential = new URLCredential($username);
        }
    }

    #[Override]
    public static function isSupported(AuthenticationScheme $scheme): bool
    {
        return $scheme === AuthenticationScheme::bearer;
    }
}
