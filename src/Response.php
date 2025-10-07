<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;

/**
 * A service response.
 */
class Response extends HTTPURLResponse
{
    public Emitter $emitter {
        get => $this->emitter ??= new Emitter();
    }
    /** @var string|null The response body. */
    public ?string $body {
        get => $this->responder->content;
    }

    public function __construct(private readonly Responder $responder)
    {
        $responder = $this->responder;
        $request = $responder->request;
        $headerFields = $responder->headerFields;
        if ($origin = $request->valueForHttpHeaderField("Origin")) {
            $headerFields["Access-Control-Allow-Origin"] = $origin;
            $headerFields["Access-Control-Allow-Credentials"] = "true";
            $headerFields["Vary"] = "Origin";
        }
        if ($value = $request->valueForHttpHeaderField("Access-Control-Request-Method")) {
            $headerFields["Access-Control-Allow-Methods"] = $value;
        }
        if ($value = $request->valueForHttpHeaderField("Access-Control-Request-Headers")) {
            $headerFields["Access-Control-Allow-Headers"] = $value;
        }
        if (match ($responder->statusCode) {
            HTTPStatusCode::created, HTTPStatusCode::noContent, HTTPStatusCode::resetContent, HTTPStatusCode::notModified => true,
            default => false
        }) {
            $headerFields->removeAll(fn(mixed $e, string $k): bool => match ($k) {
                "Content-Type", "Content-Length", "Content-Disposition" => true,
                default => false
            });
        }
        parent::__construct($request->url, $responder->statusCode, headerFields: $headerFields);
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
