<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\URLCredential;

/**
 * Represents an authentication strategy for handling different authentication schemes.
 *
 * @psalm-consistent-constructor
 */
abstract class AuthenticationStrategy
{
    /** @var AuthenticationScheme The authentication scheme used for authentication. */
    abstract public AuthenticationScheme $scheme {
        get;
    }
    /** @var URLCredential|null The credential associated with the authentication process. */
    abstract public ?URLCredential $credential {
        get;
    }
    /** @var bool Indicates whether the authentication is valid. */
    abstract public bool $isValid {
        get;
    }
    /** @var ArrayClass<string> */
    protected(set) ArrayClass $scopes {
        get => $this->scopes ??= new ArrayClass();
    }
    /** @var Authorizable|null Represents the authenticated user. */
    final public ?Authorizable $authenticatedUser {
        /**
         * @throws Exception
         */
        get {
            if (isset($this->authenticatedUser)) {
                return $this->authenticatedUser;
            }
            if (!($username = $this->credential?->user)) {
                return $this->authenticatedUser = null;
            }
            return $this->authenticatedUser = $this->context->authenticationService->find($username, $this->context->serialization, $this->context->managedObjectContext);
        }
    }

    public function __construct(public readonly AuthenticationContext $context)
    {
    }

    /**
     * Checks whether the given authentication scheme is supported.
     *
     * @param AuthenticationScheme $scheme The authentication scheme to check.
     * @return bool True if the authentication scheme is supported, false otherwise.
     */
    abstract public static function isSupported(AuthenticationScheme $scheme): bool;
}
