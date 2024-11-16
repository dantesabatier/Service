<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use function Sabatier\Foundation\human_readable_value;

class NativeEmitter extends Emitter
{
    public function emit(HTTPURLResponse $response, Dictionary $headers, ?string $content = null): never
    {
        if (headers_sent()) {
            die();
        }
        foreach (["Expires", "Cache-Control", "Pragma"] as $header) {
            header_remove($header);
        }
        header(sprintf("%s %s %s", $response->httpVersion, $response->statusCode, HTTPURLResponse::localizedString($response->statusCode)));
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
