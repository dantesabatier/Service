<?php

namespace Sabatier\Service;
/**
 * @psalm-type DownloadResponseDecoratorData array{body: string, filename: string, contentType: string}
 * @internal
 */
final class DownloadResponseDecorator extends ResponseDecorator
{
    public function __construct(Response $response)
    {
        /** @var DownloadResponseDecoratorData $data */
        $data = $response->body;
        $body = $data["body"];
        $headers = $response->allHeaderFields;
        $headers["Content-Type"] = $data["contentType"];
        $headers["Content-Disposition"] = "attachment; filename=\"{$data["filename"]}\"";
        $headers["Content-Length"] = (string)strlen($body);
        $response->body = $body;
        parent::__construct($response);
    }
}
