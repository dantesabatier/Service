<?php

namespace Sabatier\Service;

interface Authorizable extends Authenticatable
{
    public ?Authorization $authorization {
        get;
    }
}
