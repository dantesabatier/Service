<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Foundation\Dictionary;

/** @internal */
final readonly class JSONRPCRequestPruner
{
    /**
     * @param list<string> $keys
     */
    public function __construct(private array $keys = ["params", "arguments"])
    {
    }

    public function prune(Dictionary $parameters): void
    {
        foreach ($this->keys as $key) {
            if (($value = $parameters[$key]) && !$value instanceof Dictionary) {
                $parameters->removeValueForKey($key);
            }
        }
    }
}
