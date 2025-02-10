<?php

namespace Sabatier\Service;

readonly class AuthorizationParameter
{
    public function __construct(public string $name, public string $value)
    {
    }
}
