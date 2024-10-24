<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use function Sabatier\Foundation\human_readable_value;

/** @internal */
readonly class BatchResponseEmitter extends ResponseEmitter
{
    public BatchResponse $batchResponse;

    public function __construct(HTTPURLResponse $response, ?string $content = null)
    {
        parent::__construct($response, $content);
        assert($response instanceof BatchResponse);
        $this->batchResponse = $response;
    }

    #[Override]
    public function execute(): never
    {
        $response = $this->batchResponse;
        header(sprintf("%s %s %s", $response->httpVersion, $response->statusCode, HTTPURLResponse::localizedString($response->statusCode)));
        flush();
        header_register_callback(function (): void {
            foreach ($this->headerFields as $key => $value) {
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
