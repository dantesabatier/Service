<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Foundation\Dictionary;
use Sabatier\Service\Request;
use Throwable;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;

/** @internal */
final readonly class MCPRequestHandler
{
    public function __construct(private JSONRPCRequestParser $parser, private MethodDispatcher $dispatcher)
    {
    }

    public function handle(Request $request): mixed
    {
        try {
            $message = $this->parser->parse($request);
            if ($message === null) {
                return null;
            }
            if ($message instanceof JSONRPCError) {
                return $message;
            }
            return $this->dispatcher->dispatch($message);
        } catch (Throwable $throwable) {
            return new JSONRPCError(JSONRPCErrorDomain, JSONRPCErrorCodeInternalError, new Dictionary([LocalizedFailureReasonErrorKey => $throwable->getMessage()]));
        }
    }
}
