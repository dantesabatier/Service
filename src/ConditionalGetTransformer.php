<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;

/**
 * Implements HTTP conditional GET semantics using ETags.
 *
 * Sets an ETag header on eligible GET responses and returns 304 Not Modified
 * when the client's If-None-Match header matches, avoiding redundant body transfer.
 */
final class ConditionalGetTransformer extends ResponseTransformer
{
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        $request = $context->request;
        $policy = $context->cachePolicy ?? Application::shared()->cachePolicy;
        if (!$policy->etagEnabled || $request === null || $request->httpMethod !== HTTPRequestMethod::get || $response->statusCode < HTTPStatusCode::ok || $response->statusCode > 299) {
            parent::__construct($response, $context);
            return;
        }
        $headers = $response->allHeaderFields;
        $cacheControl = (string)$headers["Cache-Control"];
        if (str_contains($cacheControl, "no-store")) {
            parent::__construct($response, $context);
            return;
        }
        $etag = '"' . md5(serialize($response->body)) . '"';
        $headers["ETag"] = $etag;
        $ifNoneMatch = $request->valueForHttpHeaderField("If-None-Match");
        if ($ifNoneMatch !== null && trim($ifNoneMatch) === $etag) {
            $notModified = new Response($response->url, HTTPStatusCode::notModified);
            $notModifiedHeaders = $notModified->allHeaderFields;
            $notModifiedHeaders["ETag"] = $etag;
            if ($cacheControl !== "") {
                $notModifiedHeaders["Cache-Control"] = $cacheControl;
            }
            $vary = (string)$headers["Vary"];
            if ($vary !== "") {
                $notModifiedHeaders["Vary"] = $vary;
            }
            parent::__construct($notModified, $context);
            return;
        }
        parent::__construct($response, $context);
    }
}
