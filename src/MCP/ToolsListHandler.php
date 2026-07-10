<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Service\MCP\Response\ToolsListResult;
use Sabatier\Service\MCP\Tools\ToolRegistry;

/**
 * The full tool catalogue is listed for every caller regardless of authorization —
 * entity-level RBAC, ownership and field security are enforced at call time by each tool.
 *
 * @internal
 */
final readonly class ToolsListHandler
{
    public function __construct(private ToolRegistry $registry)
    {
    }

    public function handle(/** @noinspection PhpUnusedParameterInspection */ RPCMessage $message): ToolsListResult
    {
        return new ToolsListResult($this->registry->list);
    }
}
