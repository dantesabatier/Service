<?php

namespace Sabatier\Service;

/**
 * An object that manages the content for an area on the screen.
 * @psalm-consistent-constructor
 */
readonly class View
{

    public function __construct(public string $name, public object|array $context, public Renderer $renderer)
    {
    }

    public function render(): string
    {
        return $this->renderer->render($this->name, $this->context);
    }
}
