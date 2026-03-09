<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;

/** @internal */
final class EventStreamEmitter extends StreamEmitter
{
    #[Override]
    protected function emitHeaders(Response $response, Dictionary $headers, int $contentLength = 0): void
    {
        if (headers_sent()) {
            die();
        }
        if (function_exists("apache_setenv")) {
            @apache_setenv("no-gzip", "1");
        }
        @ini_set("zlib.output_compression", "0");
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        header(sprintf("%s %s %s", $response->httpVersion, $response->statusCode, Response::localizedString($response->statusCode)));
        foreach ($headers as $key => $value) {
            $value
                |> human_readable_value(...)
                |> (fn($x) => sprintf("%s: %s", $key, $x))
                |> header(...);
        }
    }
}
