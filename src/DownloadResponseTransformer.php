<?php

namespace Sabatier\Service;
/**
 * @psalm-type DownloadResponseTransformerData array{body: string, filename: string, contentType: string}
 * @internal
 */
final class DownloadResponseTransformer extends ResponseTransformer
{
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        /** @var DownloadResponseTransformerData $data */
        $data = $response->body;
        $body = $data["body"];
        $headers = $response->allHeaderFields;
        $headers["Content-Type"] = $data["contentType"];
        $headers["Content-Disposition"] = "attachment; filename=\"{$data["filename"]}\"";
        $headers["Content-Length"] = (string)strlen($body);
        $response->body = $body;
        parent::__construct($response, $context);
    }
}
