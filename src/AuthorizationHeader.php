<?php

namespace Sabatier\Service;

/**
 * Represents an HTTP Authorization header containing authentication parameters and scheme information.
 */
final class AuthorizationHeader extends RequestHeader
{
    /** @var AuthenticationScheme $scheme The associated authentication scheme */
    private(set) AuthenticationScheme $scheme {
        get => $this->scheme ??= AuthenticationScheme::tryFrom($this->name) ?? AuthenticationScheme::basic;
    }
}
