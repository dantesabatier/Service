<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;

interface Authorizable extends Authenticatable
{
    /** @var Dictionary<ArrayClass<Authorization>> */
    public Dictionary $authorizationsByName {
        get;
    }
}
