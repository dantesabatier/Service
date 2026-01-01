<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLCredential;

/**
 * Represents an authentication strategy for handling different authentication schemes.
 *
 * @psalm-consistent-constructor
 */
abstract class Authentication
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
    protected(set) ArrayClass $technicalScopes {
        get => $this->technicalScopes ??= new ArrayClass();
    }
    /** @var ArrayClass<string> */
    protected(set) ArrayClass $authorizationScopes {
        get => $this->authorizationScopes ??= new ArrayClass();
    }
    private bool $isAuthenticatedUserResolved = false;
    /** @var Authorizable|null Represents the authenticated user. */
    final public ?Authorizable $authenticatedUser {
        /**
         * @throws Exception
         */
        get {
            if ($this->isAuthenticatedUserResolved) {
                return $this->authenticatedUser;
            }
            $this->isAuthenticatedUserResolved = true;
            if (!($username = $this->credential?->user)) {
                return $this->authenticatedUser = null;
            }
            return $this->authenticatedUser = $this->context->authenticationService->find($username, $this->context->serialization, $this->context->managedObjectContext);
        }
    }

    /**
     * Initializes a new instance of the AuthenticationStrategy class.
     *
     * @param AuthenticationContext $context The authentication context.
     * @param Dictionary<string> $environment The environment variables.
     */
    public function __construct(public readonly AuthenticationContext $context, public readonly Dictionary $environment)
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
