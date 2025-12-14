<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;

/**
 * Handles emitting HTTP responses to the client. Supports partial streaming and optional compression.
 */
class Emitter
{
    /**
     * Emits the full response to the client and terminates execution.
     *
     * @param Response $response
     * @param Dictionary $headers
     * @param string|null $content
     * @return never
     */
    public function emit(Response $response, Dictionary $headers, ?string $content = null): never
    {
        $this->emitHeaders($response, $headers, true);
        $this->emitContent($content);
        die();
    }

    /**
     * Streams the response body in chunks to the client.
     * Compatible with large payloads and optional compression.
     *
     * @param Response $response
     * @param Dictionary $headers
     * @param iterable|string $contentChunks
     * @param bool $compress
     * @return void
     */
    public function stream(Response $response, Dictionary $headers, iterable|string $contentChunks, bool $compress = true): void
    {
        $this->emitHeaders($response, $headers, $compress);
        if (is_string($contentChunks)) {
            $this->emitContent($contentChunks);
            return;
        }
        ob_end_clean();
        if ($compress) {
            ob_start("ob_gzhandler");
        }
        foreach ($contentChunks as $chunk) {
            $this->emitChunk($chunk);
        }
    }

    /**
     * Sends headers to the client.
     *
     * @param Response $response
     * @param Dictionary $headers
     * @param bool $compress
     * @return void
     */
    private function emitHeaders(Response $response, Dictionary $headers, bool $compress): void
    {
        if (headers_sent()) {
            return;
        }
        foreach (["Expires", "Cache-Control", "Pragma"] as $header) {
            header_remove($header);
        }
        header(sprintf(
            "%s %s %s",
            $response->httpVersion,
            $response->statusCode,
            Response::localizedString($response->statusCode)
        ));
        foreach ($headers as $key => $value) {
            header(sprintf("%s: %s", $key, human_readable_value($value)));
        }
        if ($compress) {
            header("Content-Encoding: gzip");
        }
    }

    /**
     * Emits the full content at once.
     *
     * @param string|null $content
     * @return void
     */
    private function emitContent(?string $content): void
    {
        if ($content === null) {
            return;
        }
        ob_end_clean();
        ob_start("ob_gzhandler");
        echo $content;
        flush();
        ob_end_flush();
    }

    /**
     * Emits a chunk of content immediately.
     *
     * @param string $chunk
     * @return void
     */
    private function emitChunk(string $chunk): void
    {
        echo $chunk;
        flush();
    }
}
