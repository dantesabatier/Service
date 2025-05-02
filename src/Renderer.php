<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Bundle;

/**
 * The Renderer class is responsible for rendering views using a provided Bundle.
 */
class Renderer
{
    public function __construct(public Bundle $bundle)
    {
    }

    /**
     * Renders a view by its name and provided context.
     *
     * @param string $name The name of the view to be rendered.
     * @param object|array $context The context data to be extracted and passed to the view.
     * @return string The rendered output of the view as a string.
     * @throws NotFoundException If the specified view does not exist.
     */
    public function render(string $name, object|array $context): string
    {
        $path = $this->bundle->url($name, "php")?->path ?? throw new NotFoundException("The view named \"$name\" does not exists");
        $context = (array)$context;
        extract($context);
        ob_start();
        /** @psalm-suppress UnresolvableInclude */
        require_once $path;
        return (string)ob_get_clean();
    }
}
