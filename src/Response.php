<?php

namespace Sabatier\Service;

use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\human_readable_value;

/**
 * A service response.
 */
class Response extends HTTPURLResponse
{
    public Emitter $emitter {
        get => $this->emitter ??= new Emitter();
    }
    public mixed $body;
    public string $description {
        get => sprintf("<Response %s> { URL: %s }{ status: %d, headers {\n%s}, body %s }", $this->hash, $this->url->absoluteString, $this->statusCode, $this->allHeaderFields->mapValues(fn(mixed $value, string $key): string => is_string($value) ? "\"$key\" = \"$value\";\n" : sprintf("\"%s\" = %s;\n", $key, human_readable_value($value)))->values->join(""), human_readable_value($this->body));
    }

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
