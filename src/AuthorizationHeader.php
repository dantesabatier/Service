<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;

/**
 * Represents an authentication token used for validating authentication parameters.
 */
readonly class AuthorizationHeader extends RequestHeader
{
    /** @var Dictionary<covariant string> */
    public Dictionary $parameters;
    /** @var AuthenticationScheme $scheme The associated authentication scheme */
    public AuthenticationScheme $scheme;

    /**
     * @param string $rawValue The raw value of the authentication parameter
     */
    public function __construct(string $rawValue)
    {
        parent::__construct($rawValue);
        preg_match_all("/(username|uri|nonce|nc|cnonce|qop|algorithm|response|opaque)=['\"]?([^'\",]+)/", $this->value, $matches);
        $this->parameters = new Dictionary(array_combine($matches[1], $matches[2]));
        $this->scheme = AuthenticationScheme::tryFrom(ucfirst($this->name)) ?? AuthenticationScheme::basic;
    }
}
