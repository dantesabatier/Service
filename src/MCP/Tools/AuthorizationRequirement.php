<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Sabatier\Service\AuthorizationType;

/**
 * A permission a tool call needs: an action on a resource.
 */
final readonly class AuthorizationRequirement
{
    /** @var string The `resource:action` key identifying the requirement. */
    public string $key;

    /**
     * @param string $resource An entity name, or a coarse resource such as `Jobs`.
     * @param AuthorizationType $action
     */
    public function __construct(public string $resource, public AuthorizationType $action)
    {
        $this->key = "$resource:{$action->name}";
    }
}
