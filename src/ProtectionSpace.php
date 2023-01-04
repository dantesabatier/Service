<?php

namespace Sabatier\Service;

abstract class ProtectionSpace extends Responder
{
    public string $authenticationMethod;
    public string $defaultAuthenticationMethod;
    public ?string $username = null;
}
