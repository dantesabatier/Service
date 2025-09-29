<?php

namespace Sabatier\Service;

interface AuthenticationService
{
    public Authentication $authentication {
        get;
    }
    public bool $isProtectedContentAvailable {
        get;
    }
}
