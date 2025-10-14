<?php

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLCredential;

/**
 * Represents a class for managing authentication mechanisms.
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
    /** @var Authorizable|null Represents the authenticated user. */
    public ?Authorizable $user {
        get {
            if (!($username = $this->credential?->user)) {
                return null;
            }
            return $this->authenticationService->find($this->context, $username, $this->serialization);
        }
    }
    /** @var bool Indicates whether the authentication is valid. */
    abstract public bool $isValid {
        get;
    }

    public function __construct(public readonly Request $request, public readonly ManagedObjectContext $context, public readonly ?Dictionary $serialization, public readonly AuthenticationService $authenticationService)
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
