<?php

namespace Sabatier\Service;

abstract class ProtectionSpace extends Responder
{
    public ?string $authenticationMethod = null;
    public ?string $username = null;
}
