<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;

interface Authorizable extends Authenticatable
{
    /** @var ArrayClass<Authorization> */
    public ArrayClass $authorizations {
        get;
    }
}
