<?php

namespace Sabatier\Service;

interface Authorization
{
    public ?string $name {
        get;
    }
    public AuthorizationType $type {
        get;
    }
}
