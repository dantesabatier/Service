<?php

namespace Sabatier\Service;

/**
 * A policy that defines public access to resources bypassing checks and enforcement of protected content.
 *
 * Extends the AccessPolicy base class to provide implementation where all requests are allowed unrestricted access.
 */
final class PublicAccessPolicy extends AccessPolicy
{
    public bool $isAuthorizationRequired = false;
}
