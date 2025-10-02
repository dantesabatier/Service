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
    /** @var AuthenticationScheme The authentication scheme */
    abstract public AuthenticationScheme $scheme {
        get;
    }
    /** @var URLCredential|null Allows subclasses to represent and manage authentication credentials uniquely. */
    abstract public ?URLCredential $credential {
        get;
    }
    private IdentityFinder $identityFinder {
        get => $this->identityFinder ??= new IdentityFinder($this->context);
    }
    /** @var Authorizable|null Provides a mechanism for resolving and associating a user entity with an authenticated request. */
    public ?Authorizable $user {
        get {
            if (!($username = $this->credential?->user)) {
                return null;
            }
            return $this->identityFinder->find($username, $this->serialization);
        }
    }
    /** @var bool Validates a request's authentication state. */
    abstract public bool $isValid {
        get;
    }

    public function __construct(public readonly Request $request, public readonly ManagedObjectContext $context, public readonly ?Dictionary $serialization = null)
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
