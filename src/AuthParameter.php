<?php

namespace Sabatier\Service;

readonly class AuthParameter
{
    public function __construct(public string $name, public string $value)
    {
    }
}
