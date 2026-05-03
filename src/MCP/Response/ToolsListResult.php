<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Response;

use JsonSerializable;
use Override;
use Sabatier\Foundation\ArrayClass;

/**
 * Result returned by the MCP "tools/list" method.
 */
final readonly class ToolsListResult implements JsonSerializable
{
    /** @param ArrayClass<ToolDescriptor> $tools */
    public function __construct(public ArrayClass $tools)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["tools" => $this->tools];
    }
}
