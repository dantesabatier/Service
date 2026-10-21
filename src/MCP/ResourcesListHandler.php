<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Service\MCP\Resources\ResourceRegistry;
use Sabatier\Service\MCP\Response\ResourcesListResult;

/**
 * Lists the published resources. A resource is a template carrying no data, so the catalogue is the same for every
 * caller; what a UI then shows is fetched through `tools/call`, where the caller's own rules apply.
 *
 * @internal
 */
final readonly class ResourcesListHandler
{
    public function __construct(private ResourceRegistry $registry)
    {
    }

    public function handle(/** @noinspection PhpUnusedParameterInspection */ RPCMessage $message): ResourcesListResult
    {
        return new ResourcesListResult($this->registry->list);
    }
}
