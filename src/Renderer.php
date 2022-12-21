<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Bundle;

/**
 * @psalm-consistent-constructor
 */
abstract class Renderer
{
    public function __construct(public readonly Bundle $bundle)
    {
    }

    abstract public function render(string $name, object|array $context): string;
}
