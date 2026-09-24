<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use JsonException;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\Application;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Schema\EntitySchema;
use Sabatier\Service\MCP\Schema\RelationshipSchema;
use function Sabatier\Foundation\fatal_error;

/**
 * Describes the data model to the LLM client. Called first, before any other tool.
 *
 * The full schema of a large model runs to tens of thousands of tokens — more than a
 * single tool result can carry — so a call with no argument returns a lightweight index
 * instead: every entity keyed by name, with its class, label, aliases and the counts of
 * its attributes and relationships, plus the predicate syntax guide. The client then
 * requests the full detail for the entities it needs by passing `entity`, one name or a
 * list of them.
 *
 * @internal
 */
final class DescribeModelTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "describe_model";
    }
    #[Override]
    public bool $isReadOnly {
        get => true;
    }

    #[Override]
    public bool $isOpenWorld {
        get => false;
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => [
                "entity" => ["type" => "array", "items" => ["type" => "string"], "description" => "Omit for a lightweight index of every entity (class, label, aliases and attribute/relationship counts). Pass a JSON array of entity names — [\"Order\"] for one, [\"Order\", \"Customer\"] for several — for the full attributes, relationships and enum cases of those entities. Must be a real array of strings, never a single bracketed string."],
            ],
            "required" => [],
        ];
    }
    /** @var array<string, mixed> */
    private array $index {
        get {
            if (isset($this->index)) {
                return $this->index;
            }
            $schema = $this->descriptor->schema;
            $entities = $this->readableEntities->mapValues(fn(EntitySchema $entity): array => [
                "class" => $entity->className,
                "es" => $entity->label,
                "aliases" => $entity->aliases,
                "attributes" => $entity->attributes->count,
                "relationships" => $entity->relationships->count,
            ]);
            return $this->index = [
                "entities" => $entities,
                "predicate_syntax" => $schema->predicateGuide,
                "usage" => "Call describe_model with {\"entity\": [\"Order\"]} — or {\"entity\": [\"Order\", \"Customer\"]} for several — for the full attributes, relationships and enum cases of those entities. \"entity\" is always a JSON array of strings, never a bare string.",
            ];
        }
    }

    /** @var Dictionary<EntitySchema> The entities the caller may read. */
    private Dictionary $readableEntities {
        get {
            if (isset($this->readableEntities)) {
                return $this->readableEntities;
            }
            $entities = $this->descriptor->schema->entities;
            if (!$this->isSecurityEnabled) {
                return $this->readableEntities = $entities;
            }
            $readable = $entities->filter(fn(EntitySchema $entity, string $name): bool => $this->isReadable($name));
            return $this->readableEntities = $readable->mapValues(fn(EntitySchema $entity): EntitySchema => new EntitySchema($entity->name, $entity->className, $entity->label, $entity->aliases, $entity->attributes, $entity->relationships->filter(fn(RelationshipSchema $relationship): bool => $readable->offsetExists($relationship->target)), $entity->abstract));
        }
    }

    #[Override]
    public function authorizationRequirements(Dictionary $arguments): ?AuthorizationRequirements
    {
        return AuthorizationRequirements::none();
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws JsonException
     */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        $entity = $arguments["entity"];
        $names = $entity instanceof ArrayClass ? $entity : ($entity === null ? new ArrayClass() : new ArrayClass([$entity]));
        return $names->isEmpty ? $this->jsonResult($this->index) : $this->jsonResult($this->detail($names));
    }

    /**
     * @param ArrayClass<string> $names
     * @return array<string, mixed>
     */
    private function detail(ArrayClass $names): array
    {
        $schema = $this->descriptor->schema;
        $readable = $this->readableEntities;
        $entities = $names->reduce(new Dictionary(),
            /**
             * @param Dictionary<EntitySchema> $carry
             * @param string $name
             * @return Dictionary<EntitySchema>
             */
            function (Dictionary $carry, string $name) use ($readable): Dictionary {
                $carry[$name] = $readable[$name] ?? fatal_error("Unknown entity: \"$name\". Call describe_model with no argument for the list of entities.");
                return $carry;
            });
        return ["entities" => $entities, "predicate_syntax" => $schema->predicateGuide];
    }

    /**
     * @throws Exception
     */
    private function isReadable(string $entityName): bool
    {
        $user = $this->user;
        return $user !== null && Application::shared()->authorizationService->isAuthorized($user, $entityName, AuthorizationType::read, $this->fieldSecurityPolicy->scopes, $this->context);
    }
}
