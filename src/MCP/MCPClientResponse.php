<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Foundation\Dictionary;

/** One complete response returned by an MCP transport before its JSON-RPC envelope is interpreted. */
final readonly class MCPClientResponse
{
    /** @var Dictionary<mixed> The response headers. */
    public Dictionary $headers;

    /**
     * @param int $statusCode The transport status code.
     * @param Dictionary<mixed> $headers The response headers.
     * @param string $body The unparsed response body.
     */
    public function __construct(public int $statusCode, Dictionary $headers = new Dictionary(), public string $body = "")
    {
        $this->headers = clone $headers;
    }
}
