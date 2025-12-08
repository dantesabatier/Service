<?php

namespace Sabatier\Service;

/**
 * Represents an HTTP Authorization header containing authentication parameters and scheme information.
 */
class AuthorizationHeader extends RequestHeader
{
    /** @var AuthenticationScheme $scheme The associated authentication scheme */
    public readonly AuthenticationScheme $scheme;

    /**
     * @param string $rawValue The raw value of the authentication parameter
     */
    public function __construct(string $rawValue)
    {
        parent::__construct($rawValue);
        $this->scheme = AuthenticationScheme::tryFrom($this->name) ?? AuthenticationScheme::basic;
    }
}
