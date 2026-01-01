<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;

/** @internal */
final readonly class AuthorizationContext
{
    /**
     * @param ArrayClass<string> $scopes
     */
    public function __construct(public ArrayClass $scopes, public Authorizable $user)
    {
    }
}
