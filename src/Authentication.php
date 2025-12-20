<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\URLCredential;

/**
 * Represents a class for managing authentication mechanisms.
 */
class Authentication
{
    /** @var AuthenticationScheme The authentication scheme used for authentication. */
    public AuthenticationScheme $scheme {
        get => $this->strategy->scheme;
    }
    /** @var URLCredential|null The credential associated with the authentication process. */
    public ?URLCredential $credential {
        get => $this->strategy->credential;
    }
    /** @var bool Indicates whether the authentication is valid. */
    public bool $isValid {
        get => $this->strategy->isValid;
    }
    /** @var ArrayClass<string> The scopes associated with the authentication process. */
    public ArrayClass $scopes {
        get => $this->strategy->scopes;
    }
    /** @var Authorizable|null Represents the authenticated user. */
    public ?Authorizable $authenticatedUser {
        /**
         * @throws Exception
         */
        get => $this->strategy->authenticatedUser;
    }

    public function __construct(public readonly AuthenticationStrategy $strategy)
    {
    }
}
