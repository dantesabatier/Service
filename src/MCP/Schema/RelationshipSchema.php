<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Schema;

use JsonSerializable;
use Override;
use Sabatier\CoreData\DeleteRule;

/**
 * Describes a relationship on an entity in the model schema returned by describe_model.
 */
final readonly class RelationshipSchema implements JsonSerializable
{
    public function __construct(public string $name, public string $target, public bool $toMany, public bool $nullable, public DeleteRule $deleteRule = DeleteRule::nullifyDeleteRule)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        $deleteRule = match ($this->deleteRule) {
            DeleteRule::noActionDeleteRule => "noAction",
            DeleteRule::nullifyDeleteRule => "nullify",
            DeleteRule::cascadeDeleteRule => "cascade",
            DeleteRule::denyDeleteRule => "deny",
        };
        return ["target" => $this->target, "toMany" => $this->toMany, "nullable" => $this->nullable, "deleteRule" => $deleteRule];
    }
}
