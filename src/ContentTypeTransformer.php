<?php /** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Sabatier\Foundation\URLFileTypeMappings;

/** @internal */
final class ContentTypeTransformer extends ResponseTransformer
{
    public function __construct(Response $response)
    {
        $headers = $response->allHeaderFields;
        $headers["Content-Type"] ??= URLFileTypeMappings::shared()->mimeType($response->url->pathExtension);
        parent::__construct($response);
    }
}
