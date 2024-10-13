<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use function Sabatier\Foundation\human_readable_value;

/** @internal */
readonly class Response
{
    private Dictionary $headers;
    private bool $isEmpty;

    public function __construct(private Responder $responder, private HTTPURLResponse $response)
    {
        $this->isEmpty = match ($response->statusCode) {
            HTTPStatusCode::created, HTTPStatusCode::noContent, HTTPStatusCode::resetContent, HTTPStatusCode::notModified => true,
            default => $response instanceof BatchResponse ? $response->isEmpty : match ($this->responder->request->httpMethod) {
                HTTPRequestMethod::options, HTTPRequestMethod::head, HTTPRequestMethod::delete => true,
                default => empty($this->content)
            }
        };
        $headerFields = $response->allHeaderFields;
        $headerFields["Content-Type"] = $this->responder->contentType;
        $headerFields["Content-Length"] = $this->responder->contentLength;
        $headerFields["Content-Disposition"] = $this->responder->contentDisposition;
        if ($origin = $this->responder->request->valueForHttpHeaderField("Origin")) {
            $headerFields["Access-Control-Allow-Origin"] = $origin;
            $headerFields["Access-Control-Allow-Credentials"] = true;
            $headerFields["Vary"] = "Origin";
        }
        if ($value = $this->responder->request->valueForHttpHeaderField("Access-Control-Request-Method")) {
            $headerFields["Access-Control-Allow-Methods"] = $value;
        }
        if ($value = $this->responder->request->valueForHttpHeaderField("Access-Control-Request-Headers")) {
            $headerFields["Access-Control-Allow-Headers"] = $value;
        }
        if ($this->isEmpty) {
            $headerFields->removeAll(fn(mixed $e, string $k): bool => match ($k) {
                "Content-Type", "Content-Length", "Content-Disposition" => true,
                default => false
            });
        }
        $this->headers = $headerFields;
    }

    public static function from(Responder $responder, HTTPURLResponse $response): Response
    {
        return new Response($responder, $response);
    }

    private function willSend(): void
    {
        if (headers_sent()) {
            die();
        }
        foreach (["Expires", "Cache-Control", "Pragma"] as $header) {
            header_remove($header);
        }
    }

    private function sendBatchResponse(): never
    {
        /** @var BatchResponse $response */
        $response = $this->response;
        header(sprintf("%s %s %s", $response->httpVersion, $response->statusCode, HTTPURLResponse::localizedString($response->statusCode)));
        flush();
        header_register_callback(function (): void {
            foreach ($this->headers as $key => $value) {
                header(sprintf("%s: %s", $key, human_readable_value($value)));
                flush();
            }
        });
        if ($this->isEmpty) {
            die();
        }
        ob_start();
        foreach ($response as $idx => $data) {
            echo $data;
            if (($idx + 1) < $response->count) {
                echo "\r\n";
            }
            flush();
        }
        ob_end_flush();
        die();
    }

    private function sendResponse(): never
    {
        $response = $this->response;
        header(sprintf("%s %s %s", $response->httpVersion, $response->statusCode, HTTPURLResponse::localizedString($response->statusCode)));
        foreach ($this->headers as $key => $value) {
            header(sprintf("%s: %s", $key, human_readable_value($value)));
        }
        if ($this->isEmpty) {
            die();
        }
        ob_start();
        ob_start("ob_gzhandler");
        echo $this->responder->content;
        ob_end_flush();
        header("Content-Length: " . ob_get_length());
        ob_end_flush();
        die();
    }

    public function send(): never
    {
        $this->willSend();
        if ($this->response instanceof BatchResponse) {
            $this->sendBatchResponse();
        }
        $this->sendResponse();
    }
}
