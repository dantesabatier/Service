<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Resources\ResourceRegistry;
use Sabatier\Service\MCP\Response\ReadResourceResult;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;

/** @internal */
final readonly class ResourcesReadHandler
{
    public function __construct(private ResourceRegistry $registry)
    {
    }

    /**
     * Answers with the contents of the resource the request names.
     *
     * A uri the registry does not hold is reported as `InvalidParams` rather than as empty contents: the caller asked
     * for something that does not exist, and answering with nothing would read as a resource that happens to be blank.
     *
     * @param RPCMessage $message The parsed `resources/read` request carrying the uri in its params.
     * @return ReadResourceResult|JSONRPCError The contents, or an error when the uri is missing or unknown.
     */
    public function handle(RPCMessage $message): ReadResourceResult|JSONRPCError
    {
        if (!($uri = $message->params["uri"])) {
            return new JSONRPCError(JSONRPCErrorDomain, JSONRPCErrorCodeInvalidParams, new Dictionary([LocalizedFailureReasonErrorKey => "Uri is required"]));
        }
        $resource = $this->registry->resource((string)$uri);
        if ($resource === null) {
            return new JSONRPCError(JSONRPCErrorDomain, JSONRPCErrorCodeInvalidParams, new Dictionary([LocalizedFailureReasonErrorKey => "Unknown resource: $uri"]));
        }
        return new ReadResourceResult(new ArrayClass([$resource->read()]));
    }
}
