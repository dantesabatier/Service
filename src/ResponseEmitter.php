<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Throwable;
use function Sabatier\Foundation\human_readable_value;

/** @internal */
readonly class ResponseEmitter
{
    public Dictionary $headerFields;

    public function __construct(public HTTPURLResponse $response, public ?string $content = null)
    {
        $headerFields = $response->allHeaderFields;
        if (match ($response->statusCode) {
            HTTPStatusCode::created, HTTPStatusCode::noContent, HTTPStatusCode::resetContent, HTTPStatusCode::notModified => true,
            default => false
        }) {
            $headerFields->removeAll(fn(mixed $e, string $k): bool => match ($k) {
                "Content-Type", "Content-Length", "Content-Disposition" => true,
                default => false
            });
        }
        $this->headerFields = $headerFields;
    }

    public static function from(HTTPURLResponse|Throwable $response, ?string $content = null): ResponseEmitter
    {
        if ($response instanceof Throwable) {
            return new ThrowableResponseEmitter($response);
        }
        if ($response instanceof BatchResponse) {
            return new BatchResponseEmitter($response);
        }
        return new ResponseEmitter($response, $content);
    }

    public function prepare(): void
    {
        if (headers_sent()) {
            die();
        }
        foreach (["Expires", "Cache-Control", "Pragma"] as $header) {
            header_remove($header);
        }
    }

    public function execute(): never
    {
        $response = $this->response;
        header(sprintf("%s %s %s", $response->httpVersion, $response->statusCode, HTTPURLResponse::localizedString($response->statusCode)));
        foreach ($this->headerFields as $key => $value) {
            header(sprintf("%s: %s", $key, human_readable_value($value)));
        }
        ob_start();
        ob_start("ob_gzhandler");
        echo $this->content;
        ob_end_flush();
        header("Content-Length: " . ob_get_length());
        ob_end_flush();
        die();
    }

    public function emit(): never
    {
        $this->prepare();
        $this->execute();
    }
}
