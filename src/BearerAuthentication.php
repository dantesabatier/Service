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
    public AuthenticationScheme $scheme {
        get => AuthenticationScheme::bearer;
    }
    private(set) ?URLCredential $credential = null;
    private(set) bool $isValid {
        get => $this->isValid ??= $this->credential !== null;
    }
    private(set) ?JSONWebToken $token = null;
    /** @var ArrayClass<string> */
    protected(set) ArrayClass $technicalScopes {
        get => $this->technicalScopes ??= new ArrayClass($this->token?->payload?->scp ?? []);
    }
    /** @var ArrayClass<string> */
    protected(set) ArrayClass $authorizationScopes {
        get => $this->authorizationScopes ??= new ArrayClass($this->token?->payload?->authz ?? []);
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
        if ($username = $this->token?->payload?->sub) {
            $this->credential = new URLCredential($username);
        }
    }

    #[Override]
    public static function isSupported(AuthenticationScheme $scheme): bool
    {
        return $scheme === AuthenticationScheme::bearer;
    }
}
