<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;

/** @internal */
final class ChunkedEmitter extends Emitter
{
    #[Override]
    public function emit(Response $response, Dictionary $headers, ?string $content = null, bool $useCompression = true): never
    {
        assert($response instanceof ChunkedResponse);
        if (headers_sent()) {
            die("Headers already sent");
        }
        header(sprintf("%s %s %s", $response->httpVersion, $response->statusCode, Response::localizedString($response->statusCode)));
        header_register_callback(function () use ($headers): void {
            foreach ($headers as $key => $value) {
                header(sprintf("%s: %s", $key, human_readable_value($value)));
                flush();
            }
        });
        foreach ($response as $chunk) {
            echo "$chunk\n";
            flush();
        }
        echo "\n";
        flush();
        die();
    }
}
