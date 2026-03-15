<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\UserDefaults;

/** @internal */
#[Endpoint("Preferences", decorators: [JSONDecorator::class])]
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

    #[Action(HTTPRequestMethod::patch, decorators: [JSONDecorator::class])]
    public function synchronize(): void
    {
        $body = $this->request->parsedBody;
        foreach ($body as $key => $value) {
            UserDefaults::standard()->setObject($value, $key);
        }
    }
}
