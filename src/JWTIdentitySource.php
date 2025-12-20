<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;

/** @internal */
class JWTIdentitySource extends IdentitySource
{
    /** @var ArrayClass<string> */
    protected(set) ArrayClass $scopes {
        get => $this->scopes ??= new ArrayClass($this->token->payload->scopes ?? []);
    }
    private readonly JSONWebToken $token;

    public function __construct(?Authorizable $subject, JSONWebToken $token)
    {
        parent::__construct($subject);
        $this->token = $token;
    }
}
