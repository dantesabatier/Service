<?php

namespace Sabatier\Service;

readonly class AuthParameter
{
    public AuthenticationScheme $scheme;

    public function __construct(public string $name, public string $value)
    {
        $this->scheme = AuthenticationScheme::tryFrom(ucfirst($this->name)) ?? AuthenticationScheme::basic;
    }
}
