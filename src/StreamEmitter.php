<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;

/** @internal */
class StreamEmitter extends Emitter
{
    #[Override]
    public function emit(Response $response, Dictionary $headers, ?string $content = null, bool $useCompression = true): never
    {
        $this->emitHeaders($response, $headers);
        $this->emitContent($response);
        exit(0);
    }

    #[Override]
    protected function emitHeaders(Response $response, Dictionary $headers, int $contentLength = 0): void
    {
        if (headers_sent()) {
            die("Headers already sent");
        }
        if (function_exists("apache_setenv")) {
            @apache_setenv("no-gzip", "1");
        }
        @ini_set("zlib.output_compression", "0");
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        header(sprintf("%s %s %s", $response->httpVersion, $response->statusCode, Response::localizedString($response->statusCode)));
        header_register_callback(function () use ($headers): void {
            foreach ($headers as $key => $value) {
                $value
                    |> human_readable_value(...)
                    |> (fn(string $x): string => sprintf("%s: %s", $key, $x))
                    |> header(...);
                flush();
            }
        });
    }

    #[Override]
    protected function emitContent(mixed $content, bool $useCompression = true): void
    {
        foreach ($content as $chunk) {
            echo "$chunk\n";
            flush();
        }
    }
}
