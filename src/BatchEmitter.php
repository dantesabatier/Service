<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use function Sabatier\Foundation\human_readable_value;

class BatchEmitter extends Emitter
{
    public function emit(BatchResponse $response, Dictionary $headers, ?string $content = null): never
    {
        if (headers_sent()) {
            die();
        }
        foreach (["Expires", "Cache-Control", "Pragma"] as $header) {
            header_remove($header);
        }
        header(sprintf("%s %s %s", $response->httpVersion, $response->statusCode, HTTPURLResponse::localizedString($response->statusCode)));
        flush();
        header_register_callback(function () use ($headers): void {
            foreach ($headers as $key => $value) {
                header(sprintf("%s: %s", $key, human_readable_value($value)));
                flush();
            }
        });
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
}
