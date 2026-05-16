<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use JsonException;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Schema\AttributeSchema;
use Sabatier\Service\MCP\Schema\EntitySchema;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Schema\RelationshipSchema;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\human_readable_value;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

/**
 * Base class for all MCP tools, both built-in and custom.
 *
 * Extend this class and place the subclass in the application's `MCPTools`
 * directory to register a custom tool with the MCP server.
 *
 * @psalm-consistent-constructor
 * @phpstan-consistent-constructor
 */
abstract class AbstractTool
{
    abstract public string $name {
        get;
    }
    abstract public string $description {
        get;
    }
    abstract public array $inputSchema {
        get;
    }

    public function __construct(protected readonly ManagedObjectContext $context, protected readonly ModelDescriptor $descriptor)
    {
    }

    /** @return ArrayClass<ContentItem> */
    abstract public function execute(Dictionary $arguments): ArrayClass;

    protected function entity(string $name): EntitySchema
    {
        /** @var EntitySchema */
        return $this->descriptor->schema->entities[$name] ?? fatal_error("Unknown entity: \"$name\"");
    }

    protected function validateKeyPath(string $entityName, string $keyPath): void
    {
        $parts = explode(".", $keyPath);
        $current = $this->entity($entityName);
        $last = count($parts) - 1;
        foreach ($parts as $index => $part) {
            if ($index === $last) {
                if (!$current->attributes[$part] && !$current->relationships[$part]) {
                    fatal_error("Unknown property \"$part\" on $current->name. Attributes: {$current->attributes->keys}. Relationships: {$current->relationships->keys}.");
                }
                return;
            }
            /** @var RelationshipSchema $relationship */
            $relationship = $current->relationships[$part] ?? fatal_error("\"$part\" is not a relationship on $current->name. Relationships: {$current->relationships->keys}.");
            $current = $this->entity($relationship->target);
        }
    }

    /**
     * @param string $entityName
     * @param string $format
     * @param ArrayClass<string> $arguments
     */
    protected function validatePredicateKeyPaths(string $entityName, string $format, ArrayClass $arguments): void
    {
        preg_match_all("/%[K@sdf]/", $format, $matches);
        $placeholders = new ArrayClass($matches[0]);
        $arguments->count === $placeholders->count ?: $arguments
                |> human_readable_value(...)
                |> (fn(string $x): string => sprintf("Invalid predicate: %d argument(s) provided but %d placeholder(s) found in \"%s\". Each placeholder (%s) requires exactly one argument in the same position.\n%s", $arguments->count, $placeholders->count, $format, $placeholders->join(", "), $x))
                |> fatal_error(...);
        foreach ($placeholders as $index => $placeholder) {
            if ($placeholder !== "%K") {
                continue;
            }
            $keyPath = $arguments[$index];
            $this->validateKeyPath($entityName, $keyPath);
            $attribute = $this->resolveAttribute($entityName, $keyPath);
            if (!$attribute?->enum) {
                continue;
            }
            if ($arguments->count <= $index + 1) {
                continue;
            }
            $value = $arguments[$index + 1];
            $cases = $attribute->enum->cases;
            $invalid = $value instanceof ArrayClass ? $value->filter(fn(mixed $v): bool => !$cases->containsElement($v)) : (!$cases->containsElement($value) ? new ArrayClass([$value]) : new ArrayClass());
            if ($invalid->isEmpty) {
                continue;
            }
            $map = $cases->map(fn(string|int $v, string $k): string => "\"$k\" → $v")->join(", ");
            fatal_error(sprintf("Invalid enum value for \"%s\": %s. Pass the mapped value, not the case name. Cases: %s", $keyPath, $invalid->description, $map));
        }
    }

    private function resolveAttribute(string $entityName, string $keyPath): ?AttributeSchema
    {
        $parts = explode(".", $keyPath);
        $current = $this->entity($entityName);
        $last = count($parts) - 1;
        foreach ($parts as $index => $part) {
            if ($index === $last) {
                return $current->attributes[$part] ?? null;
            }
            /** @var RelationshipSchema|null $relationship */
            $relationship = $current->relationships[$part] ?? null;
            if (!$relationship) {
                return null;
            }
            $current = $this->entity($relationship->target);
        }
        return null;
    }

    protected function fetchRequest(string $entityName): FetchRequest
    {
        $model = $this->context->persistentStoreCoordinator?->managedObjectModel ?? fatal_error("No managed object model");
        $entity = $model->entitiesByName[$entityName] ?? fatal_error("Unknown entity: $entityName");
        $request = new FetchRequest();
        $request->entity = $entity;
        return $request;
    }

    protected function buildPredicate(string $format, ArrayClass $arguments): Predicate
    {
        return Predicate::format($format, $arguments) ?? fatal_error("Invalid predicate format");
    }

    protected function normalizeRelationships(string $entityName, Dictionary $values): Dictionary
    {
        $schema = $this->entity($entityName);
        $normalized = clone $values;
        foreach ($values as $key => $value) {
            if ($schema->relationships[$key] && is_numeric($value)) {
                $normalized[$key] = new Dictionary([ManagedObjectObjectIDKey => $value]);
            }
        }
        return $normalized;
    }

    /**
     * Resolves $WEEK_START, $WEEK_END, $MONTH_START, $MONTH_END in a predicate arguments array.
     * Handles one level of nesting (e.g. BETWEEN ["$WEEK_START","$WEEK_END"]).
     *
     * @param ArrayClass<mixed> $params
     * @return ArrayClass<mixed>
     */
    protected function resolveVariables(ArrayClass $params): ArrayClass
    {
        $now = new \DateTime();
        $isoYear = (int)$now->format('o');
        $isoWeek = (int)$now->format('W');
        $vars = new Dictionary([
            '$WEEK_START' => (new \DateTime())->setISODate($isoYear, $isoWeek)->format('Y-m-d'),
            '$WEEK_END' => (new \DateTime())->setISODate($isoYear, $isoWeek, 7)->format('Y-m-d'),
            '$MONTH_START' => $now->format('Y-m-01'),
            '$MONTH_END' => $now->format('Y-m-t'),
        ]);
        $resolve = fn(mixed $v): mixed => is_string($v) && $vars[$v] !== null ? $vars[$v] : $v;
        return $params->map(fn(mixed $value): mixed => $value instanceof ArrayClass ? $value->map($resolve) : $resolve($value));
    }

    /** @return ArrayClass<ContentItem> */
    protected function textResult(string $text): ArrayClass
    {
        return new ArrayClass([new ContentItem("text", $text)]);
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws JsonException
     */
    protected function jsonResult(mixed $data): ArrayClass
    {
        return $this->textResult(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
