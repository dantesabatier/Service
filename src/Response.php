<?php

namespace Sabatier\Service;

use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\URL;

/**
 * A service response.
 */
class Response extends HTTPURLResponse
{
    public Emitter $emitter {
        get => $this->emitter ??= new Emitter();
    }
    public mixed $body;

    public function __construct(URL $url, #[ExpectedValues(valuesFromClass: HTTPStatusCode::class)] int $statusCode = HTTPStatusCode::ok, Dictionary $headerFields = new Dictionary(), mixed $body = null)
    {
        parent::__construct($url, $statusCode, headerFields: $headerFields);
        $this->body = $body;
    }

    /**
     * Sends the current instance by emitting it along with associated header fields and body content.
     *
     * @return never
     */
    public function send(): never
    {
        $this->emitter->emit($this, $this->allHeaderFields, $this->body);
    }
}
