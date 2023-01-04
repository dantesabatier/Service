<?php

namespace Sabatier\Service;

abstract class Protection extends Responder
{
    public ?string $method = null;
    public ?string $username = null;
}
