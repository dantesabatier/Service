<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;

/** @internal */
final readonly class AuthorizationContext
{
    /**
     * @param Authorizable|null $user
     * @param ArrayClass<string> $scopes
     * @param bool $isSecurityEnabled
     */
    public function __construct(public ?Authorizable $user, public ArrayClass $scopes, public bool $isSecurityEnabled)
    {
    }
}
