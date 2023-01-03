<?php

namespace Sabatier\Service;

abstract class Protection extends Responder
{
    abstract public function isValid(): bool;
    abstract public function username(): ?string;
}
