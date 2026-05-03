<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;

final class JSONRPCTransformer extends ResponseTransformer
{
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        $body = $response->body;
        $error = $body instanceof Error ? $body : null;
        $response->body = new Dictionary(["jsonrpc" => "2.0", "id" => $context->request?->parameters?->valueForKey("id"), "result" => $error ? null : $body, "error" => $error ?: null]);
        parent::__construct($response, $context);
    }
}
