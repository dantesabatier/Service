<?php

namespace Sabatier\Service;

/**
 * An object that manages the content for an area on the screen.
 * @psalm-consistent-constructor
 */
readonly class View
{
    public function __construct(public string $name, public array $context, public Renderer $renderer)
    {
    }

    /**
     * Renders the output using the provided renderer, name, and context.
     *
     * @return string Returns the rendered output as a string.
     */
    public function render(): string
    {
        return $this->renderer->render($this->name, $this->context);
    }
}
