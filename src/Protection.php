<?php

namespace Sabatier\Service;

abstract class Protection extends Responder
{
    public ?string $authenticationMethod = null;
    public ?string $username = null;
}
