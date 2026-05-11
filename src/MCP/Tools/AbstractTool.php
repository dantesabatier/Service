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
                    fatal_error("Unknown property \"$part\" on $current->name. Attributes: [" . $current->attributes->keys->join(", ") . "]. Relationships: [" . $current->relationships->keys->join(", ") . "].");
                }
                return;
            }
            /** @var RelationshipSchema $relationship */
            $relationship = $current->relationships[$part] ?? fatal_error("\"$part\" is not a relationship on $current->name. Relationships: [" . $current->relationships->keys->join(", ") . "].");
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
            $this->validateKeyPath($entityName, $arguments[$index]);
        }
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
