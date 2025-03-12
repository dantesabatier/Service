<?php

namespace Sabatier\Service;

class AuthParameter
{
    private(set) AuthenticationScheme $scheme {
        get => $this->scheme ??= AuthenticationScheme::tryFrom($this->name) ?? AuthenticationScheme::basic;
    }

    public function __construct(readonly public string $name, readonly public string $value)
    {
    }
}
