<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\ObjectClass;

/**
 * @psalm-consistent-constructor
 */
class View extends ObjectClass
{
    public function __construct(public readonly string $name, public readonly object|array $context, public readonly Bundle $bundle)
    {
    }

    public function render(): string
    {
        $path = $this->bundle->url($this->name, "php")?->path ?? throw new NotFoundException();
        $context = (array)$this->context;
        foreach ($context as $key => $value) {
            $$key = $value;
        }
        ob_start();
        /** @psalm-suppress UnresolvableInclude */
        require_once $path;
        return (string)ob_get_clean();
    }

    public function description(): string
    {
        return $this->render();
    }
}
