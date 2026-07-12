<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;

/** @internal */
final readonly class AuthorizationContext
{
    /**
     * @param Authorizable|null $user
     * @param ArrayClass<string> $scopes
     * @param bool $isSecurityEnabled
     * @param Dictionary<mixed> $environment The request environment, bound to `$ENVIRONMENT` when resolving attribute-based conditions.
     * @param Dictionary<mixed> $request The request context (`ip`, `host`, `country`), bound to `$REQUEST` when resolving attribute-based conditions.
     */
    public function __construct(public ?Authorizable $user, public ArrayClass $scopes, public bool $isSecurityEnabled, public Dictionary $environment = new Dictionary(), public Dictionary $request = new Dictionary())
    {
    }
}
