<?php

declare(strict_types=1);

namespace Sabatier\Service;

use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\human_readable_value;

/**
 * A mutable HTTP response produced by the responder pipeline.
 *
 * `Response` extends `HTTPURLResponse` with a writable `$body` and an `Emitter`
 * responsible for flushing status, headers, and body to the PHP output layer.
 *
 * Response instances flow through the `ResponsePipeline` and the manually wired
 * internal transformers (`ConditionalGetTransformer`, `SecurityHeadersTransformer`,
 * `CORSResponseTransformer`). Each transformer operates on the same instance —
 * mutating headers in-place and optionally replacing the body.
 *
 * `send()` must be called exactly once, after all transformers have run. It is
 * invoked automatically by `Application::processResponse()`.
 *
 * @see ResponseTransformer
 * @see ResponsePipeline
 * @see Emitter
 */
class Response extends HTTPURLResponse
{
    public Emitter $emitter;
    #[Override]
    public string $description {
        get => sprintf("<Response %s> { URL: %s }{ status: %d, headers {\n%s}, body %s }", $this->hash, $this->url->absoluteString, $this->statusCode, $this->allHeaderFields->mapValues(fn(mixed $value, string $key): string => is_string($value) ? "\"$key\" = \"$value\";\n" : sprintf("\"%s\" = %s;\n", $key, human_readable_value($value)))->values->join(""), human_readable_value($this->body));
    }
    public mixed $body;

    public function __construct(URL $url, #[ExpectedValues(valuesFromClass: HTTPStatusCode::class)] int $statusCode = HTTPStatusCode::ok, Dictionary $headerFields = new Dictionary(), mixed $body = null)
    {
        parent::__construct($url, $statusCode, headerFields: $headerFields);
        $this->body = $body;
        $this->emitter = new Emitter();
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
