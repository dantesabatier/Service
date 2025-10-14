<?php

namespace Sabatier\Service;

/**
 * Authentication service for handling authentication-related functionalities and providing access control to protected content.
 */
interface Authenticator
{
    public Authentication $authentication {
        get;
    }
    public bool $isProtectedContentAvailable {
        get;
    }
    public Responder $responder {
        get;
    }
}
