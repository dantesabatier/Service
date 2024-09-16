<?php

namespace Sabatier\Service;

use Override;

class NativeRenderer extends Renderer
{
    #[Override]
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
