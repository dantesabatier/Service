<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;

class Response extends HTTPURLResponse
{
    public Emitter $emitter;
    public readonly ?string $body;

    public function __construct(Responder $responder)
    {
        $request = $responder->request;
        $headerFields = $responder->headerFields;
        if ($origin = $request->valueForHttpHeaderField("Origin")) {
            $headerFields["Access-Control-Allow-Origin"] = $origin;
            $headerFields["Access-Control-Allow-Credentials"] = true;
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
        $this->body = $responder->content;
        $this->emitter = new Emitter();
    }

    public function send(): never
    {
        $this->emitter->emit($this, $this->allHeaderFields, $this->body);
    }
}
