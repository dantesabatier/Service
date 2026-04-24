<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;

/**
 * Implements HTTP conditional GET semantics using ETags.
 *
 * ETag generation is opt-in: it only activates when the response already carries an
 * explicit `Cache-Control` header (meaning `CacheHeaderTransformer` ran upstream) and
 * that header does not contain `no-store`. This prevents accidental ETag emission on
 * responses that were never intended to be cached.
 *
 * Returns 304 Not Modified when the client's `If-None-Match` header matches the
 * generated ETag, supporting both comma-separated ETag lists and the `*` wildcard
 * as required by RFC 9110 §13.1.2.
 */
final class ConditionalGetTransformer extends ResponseTransformer
{
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        $request = $context->request;
        if ($request === null || $request->httpMethod !== HTTPRequestMethod::get || $response->statusCode < HTTPStatusCode::ok || $response->statusCode > 299) {
            parent::__construct($response, $context);
            return;
        }
        $headers = $response->allHeaderFields;
        $cacheControl = (string)$headers["Cache-Control"];
        if ($cacheControl === "" || str_contains($cacheControl, "no-store") || $context->cachePolicy?->etagEnabled === false) {
            parent::__construct($response, $context);
            return;
        }
        $body = $response->body;
        $etag = '"' . md5(is_string($body) ? $body : serialize($body)) . '"';
        $headers["ETag"] = $etag;
        $ifNoneMatch = $request->valueForHttpHeaderField("If-None-Match");
        if ($ifNoneMatch !== null) {
            $tags = array_map('trim', explode(',', $ifNoneMatch));
            if (in_array('*', $tags, true) || in_array($etag, $tags, true)) {
                $notModified = new Response($response->url, HTTPStatusCode::notModified);
                $notModifiedHeaders = $notModified->allHeaderFields;
                $notModifiedHeaders["ETag"] = $etag;
                $notModifiedHeaders["Cache-Control"] = $cacheControl;
                $vary = (string)$headers["Vary"];
                if ($vary !== "") {
                    $notModifiedHeaders["Vary"] = $vary;
                }
                parent::__construct($notModified, $context);
                return;
            }
        }
        parent::__construct($response, $context);
    }
}
