<?php

namespace Sabatier\Service;

interface Authorizable extends Authenticatable
{
    public bool $isEnabled {
        get;
    }
    public ?Authorization $authorization {
        get;
    }
}
