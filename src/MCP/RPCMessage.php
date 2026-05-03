<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Foundation\Dictionary;

/** @internal */
final readonly class RPCMessage
{
    /**
     * @param mixed $id
     * @param string $method
     * @param Dictionary<mixed> $params
     */
    public function __construct(public mixed $id, public string $method, public Dictionary $params)
    {
    }
}
