<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\UserDefaults;

/** @internal */
#[Endpoint("Preferences", transformers: [JSONTransformer::class, NoCacheHeaderTransformer::class])]
final class Preferences extends Responder
{
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::patch]);
    }
    #[Override]
    protected mixed $data {
        get => $this->data ??= UserDefaults::standard()->dictionaryRepresentation();
    }

    #[Action(HTTPRequestMethod::patch, transformers: [JSONTransformer::class, NoCacheHeaderTransformer::class])]
    public function synchronize(): void
    {
        $parameters = $this->request->parameters;
        foreach ($parameters as $key => $value) {
            UserDefaults::standard()->setObject($value, $key);
        }
    }
}
