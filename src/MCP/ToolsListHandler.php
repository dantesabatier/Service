<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Service\MCP\Response\ToolsListResult;
use Sabatier\Service\MCP\Tools\ToolRegistry;

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
