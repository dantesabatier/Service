<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\AuthorizationType;

/**
 * What a tool call must be authorized for before `ToolRegistry` runs it. An empty set is denied; only {@see self::none()} declares nothing.
 */
final readonly class AuthorizationRequirements
{
    /** @var ArrayClass<AuthorizationRequirement> The permissions the call needs. */
    public ArrayClass $requirements;

    /**
     * @param bool $isNone
     * @param ArrayClass<AuthorizationRequirement> $requirements
     */
    private function __construct(public bool $isNone, ArrayClass $requirements)
    {
        $this->requirements = $requirements->reduce(new Dictionary(),
            /**
             * @param Dictionary<AuthorizationRequirement> $carry
             * @return Dictionary<AuthorizationRequirement>
             */
            function (Dictionary $carry, AuthorizationRequirement $requirement): Dictionary {
                $carry[$requirement->key] = $requirement;
                return $carry;
            })->values;
    }

    /**
     * Declares that the call needs no entity-level permission.
     */
    public static function none(): AuthorizationRequirements
    {
        return new AuthorizationRequirements(true, new ArrayClass());
    }

    /**
     * @param ArrayClass<AuthorizationRequirement> $requirements
     */
    public static function of(ArrayClass $requirements): AuthorizationRequirements
    {
        return new AuthorizationRequirements(false, $requirements);
    }

    /**
     * Declares a single permission.
     */
    public static function one(string $resource, AuthorizationType $action): AuthorizationRequirements
    {
        return self::of(new ArrayClass([new AuthorizationRequirement($resource, $action)]));
    }
}
