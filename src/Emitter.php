<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;

abstract class Emitter
{
    abstract public function emit(Response $response, Dictionary $headers, ?string $content = null): never;
}
