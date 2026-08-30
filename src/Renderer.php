<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\Bundle;
use function Sabatier\Foundation\localized_string;

/**
 * The Renderer class is responsible for rendering views using a provided Bundle.
 * @psalm-consistent-constructor
 */
class Renderer
{
    /**
     * Initializes a new instance of the class with a specified bundle.
     *
     * @param Bundle $bundle The bundle used to locate and manage view files.
     */
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
        $path = $this->bundle->url($name, "php")?->path ?? throw new NotFoundException(sprintf(localized_string("The view named \"%s\" does not exist"), $name));
        $context = (array)$context;
        $context["include_view"] = fn(string $viewName, array|object $subContext = []): string => $this->render($viewName, $subContext);
        ob_start();
        (function (array $vars) use ($path): void {
            extract($vars, EXTR_SKIP);
            require $path;
        })($context);
        return (string)ob_get_clean();
    }
}
