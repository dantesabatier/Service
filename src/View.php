<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ObjectClass;

/**
 * An object that manages the content for an area on the screen.
 * @psalm-consistent-constructor
 */
class View extends ObjectClass
{
    /** @var class-string<Renderer> */
    public static string $rendererClass = NativeRenderer::class;

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
