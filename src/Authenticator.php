<?php

namespace Sabatier\Service;

/**
 * Represents an abstract authenticator responsible for managing authentication and determining the availability of protected content.
 */
abstract class Authenticator
{
    abstract public Authentication $authentication {
        get;
    }
    abstract public bool $isProtectedContentAvailable {
        get;
    }

    public function __construct(public readonly AuthenticationProtocol $protocol)
    {
    }
}
