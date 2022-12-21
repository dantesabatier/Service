<?php

namespace Sabatier\Service;

/** @internal */
class NativeRenderer extends Renderer
{
    public function render(string $name, object|array $context): string
    {
        $path = $this->bundle->url($name, "php")?->path ?? throw new NotFoundException("Unable to load template \"$name\"");
        $context = (array)$context;
        extract($context);
        ob_start();
        /** @psalm-suppress UnresolvableInclude */
        require_once $path;
        return (string)ob_get_clean();
    }
}
