<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;

/**
 * Represents the resolved identity for the current request.
 *
 * An IdentitySource encapsulates:
 * - The authenticated subject (if any)
 * - The authorization scopes associated with that identity
 * - The lifecycle of the identity (invalidation, revocation, logout)
 *
 * IdentitySource acts as a unifying abstraction over different identity
 * mechanisms such as JWT, session-based authentication, API keys, or
 * other custom identity providers.
 *
 * Implementations are expected to:
 * - Materialize scopes once and expose them as a read-only collection
 * - treat scopes as authoritative for authorization decisions
 * - optionally override invalidate() when revocation is supported
 *
 * This class is part of the public authentication and authorization API.
 */
abstract class IdentitySource
{
    /** @var ArrayClass<string> Authorization scopes associated with the identity */
    abstract public ArrayClass $scopes {
        get;
    }

    /**
     * Creates a new identity source instance.
     *
     * @param Authorizable|null $subject The authenticated subject associated with the identity or null when the request is unauthenticated.
     */
    public function __construct(public ?Authorizable $subject)
    {
    }

    /**
     * Invalidates the identity (logout, revoke, destroy)
     */
    public function invalidate(): void
    {
    }
}
