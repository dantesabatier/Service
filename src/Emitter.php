<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;

/**
 * Handles emitting an HTTP response to the client.
 */
class Emitter
{
    /**
     * Emits the response to the client by sending headers and content and then terminates execution.
     *
     * @param Response $response The response object.
     * @param Dictionary $headers An associative array of additional headers to send.
     * @param string|null $content The body content to send in the response can be null.
     * @return never This method does not return.
     */
    public function emit(Response $response, Dictionary $headers, ?string $content = null): never
    {
        if (headers_sent()) {
            die();
        }
        foreach (["Expires", "Cache-Control", "Pragma"] as $header) {
            header_remove($header);
        }
        header(sprintf("%s %s %s", $response->httpVersion, $response->statusCode, Response::localizedString($response->statusCode)));
        foreach ($headers as $key => $value) {
            header(sprintf("%s: %s", $key, human_readable_value($value)));
        }
        ob_start();
        ob_start("ob_gzhandler");
        echo $content;
        ob_end_flush();
        header("Content-Length: " . ob_get_length());
        ob_end_flush();
        die();
    }
}
