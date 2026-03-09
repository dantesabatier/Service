<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;

/**
 * Handles emitting an HTTP response to the client, including Content-Length support.
 */
class Emitter
{
    public function emit(Response $response, Dictionary $headers, ?string $content = null, bool $useCompression = true): never
    {
        $content ??= "";
        $contentLength = strlen($content);
        $this->emitHeaders($response, $headers, $contentLength);
        $this->emitContent($content, $useCompression);
        exit(0);
    }

    protected function emitHeaders(Response $response, Dictionary $headers, int $contentLength = 0): void
    {
        if (headers_sent()) {
            die();
        }
        foreach (["Expires", "Cache-Control", "Pragma"] as $header) {
            header_remove($header);
        }
        header(sprintf("%s %s %s", $response->httpVersion, $response->statusCode, Response::localizedString($response->statusCode)));
        foreach ($headers as $key => $value) {
            $value
                |> human_readable_value(...)
                |> (fn($x) => sprintf("%s: %s", $key, $x))
                |> header(...);
        }
        if ($contentLength > 0) {
            header(sprintf("Content-Length: %d", $contentLength));
        }
    }

    protected function emitContent(mixed $content, bool $useCompression = true): void
    {
        if ($useCompression && !ob_get_level()) {
            ob_start("ob_gzhandler");
        } elseif (!ob_get_level()) {
            ob_start();
        }
        echo $content;
        ob_flush();
        flush();
    }
}
