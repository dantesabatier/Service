<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * An immutable value object that pairs a template name with its rendering context and renderer.
 *
 * `View` is created by `ViewController` after the view lifecycle callbacks (`viewWillLoad`,
 * `viewDidLoad`) have run. It holds the template name (derived from the controller's class name
 * by default), the outlet context (a `string => mixed` map of `#[Outlet]`-annotated properties),
 * and the `Renderer` instance configured by `ViewController::$rendererClass`.
 *
 * Calling `render()` delegates to the renderer and returns the fully rendered string, which
 * `ViewController` sets as `$response->body`.
 *
 * @psalm-consistent-constructor
 * @see ViewController
 * @see Renderer
 * @see Outlet
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
