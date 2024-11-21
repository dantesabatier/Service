<?php

namespace Sabatier\Service;

interface Authenticatable
{
    public ?string $username {
        get;
    }
    public ?string $password {
        get;
    }
}
