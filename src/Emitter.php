<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPURLResponse;

abstract class Emitter
{
    abstract public function emit(HTTPURLResponse $response, Dictionary $headers, ?string $content = null): never;
}
