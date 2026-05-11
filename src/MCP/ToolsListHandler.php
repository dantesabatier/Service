<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Service\MCP\Response\ToolsListResult;
use Sabatier\Service\MCP\Tools\ToolRegistry;

/** @internal */
final readonly class ToolsListHandler
{
    public function __construct(private ToolRegistry $registry)
    {
    }

    public function handle(): ToolsListResult
    {
        return new ToolsListResult($this->registry->list);
    }
}
