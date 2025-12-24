<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;

/** @internal */
class ChunkedEmitter extends Emitter
{
    #[Override]
    public function emit(Response $response, Dictionary $headers, ?string $content = null, bool $useCompression = true): never
    {
        assert($response instanceof ChunkedResponse);
        if (headers_sent()) {
            die();
        }
        header(sprintf("%s %s %s", $response->httpVersion, $response->statusCode, Response::localizedString($response->statusCode)));
        header_register_callback(function () use ($headers): void {
            foreach ($headers as $key => $value) {
                header(sprintf("%s: %s", $key, human_readable_value($value)));
            }
        });
        if (function_exists("ob_implicit_flush")) {
            ob_implicit_flush();
        }
        foreach ($response as $chunk) {
            echo $chunk, "\n";
            flush();
        }
        die();
    }
}
