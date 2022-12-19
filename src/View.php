<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\ObjectClass;

/**
 * @psalm-consistent-constructor
 */
class View extends ObjectClass
{
    public function __construct(public readonly string $name, public readonly object|array $context, public readonly Renderer $renderer)
    {
    }

    public function render(): string
    {
        return $this->renderer->render($this->name, $this->context);
    }

    public function description(): string
    {
        return $this->render();
    }
}
