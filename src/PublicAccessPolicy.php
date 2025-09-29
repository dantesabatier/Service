<?php

namespace Sabatier\Service;

/**
 * A policy that defines public access to resources bypassing checks and enforcement of protected content.
 */
final class PublicAccessPolicy extends AccessPolicy
{
    public bool $isAuthorizationRequired = false;
}
