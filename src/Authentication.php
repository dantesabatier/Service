<?php

namespace Sabatier\Service;

use const Sabatier\Foundation\Networking\URLAuthenticationMethodDefault;

abstract class Authentication extends Responder
{
    public string $method = URLAuthenticationMethodDefault;

    abstract public function isValid(): bool;

    abstract public function username(): ?string;
}
