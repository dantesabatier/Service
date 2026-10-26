<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Exception;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ExpressionDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Service\AccessConditionResolver;
use function Sabatier\Foundation\fatal_error;

/**
 * Translates the arguments of a tool call into a fetch request, as {@see \Sabatier\Service\RequestToFetchRequestAdapter} translates an HTTP request: the arguments are named after the properties they set, so there is nothing to map. It only translates — a key path that does not exist is reported by Core Data when the statement is generated, and the rules the subject is held to are applied by the operation that runs the request.
 *
 * @internal
 */
final class ToolCallToFetchRequestAdapter
{
    public FetchRequest $fetchRequest {
        /** @throws Exception */
        get => $this->fetchRequest ??= $this->build();
    }

    /**
     * @param Dictionary<mixed> $arguments The arguments the caller supplied.
     * @param ManagedObjectContext $context
     */
    public function __construct(private readonly Dictionary $arguments, private readonly ManagedObjectContext $context)
    {
    }

    /**
     * @throws Exception
     */
    private function build(): FetchRequest
    {
        $arguments = $this->arguments;
        $request = new FetchRequest();
        $request->entity = EntityDescription::entity((string)($arguments["entityName"] ?? fatal_error("entityName is required")), $this->context);
        $request->predicate = $this->predicate($arguments["predicate"]);
        $request->havingPredicate = $this->predicate($arguments["havingPredicate"]);
        $request->sortDescriptors = $this->sortDescriptors($arguments["sortDescriptors"]);
        $request->propertiesToFetch = $this->properties($arguments["propertiesToFetch"], $request->entity);
        $request->propertiesToGroupBy = $this->properties($arguments["propertiesToGroupBy"], $request->entity);
        $request->fetchLimit = (int)$arguments["fetchLimit"];
        $request->fetchOffset = (int)$arguments["fetchOffset"];
        $request->fetchBatchSize = (int)$arguments["fetchBatchSize"];
        $request->includesSubentities = (bool)($arguments["includesSubentities"] ?? true);
        $request->returnsDistinctResults = (bool)$arguments["returnsDistinctResults"];
        if ($serialization = $arguments["serialization"]) {
            $request->serialization = $serialization instanceof Dictionary ? $serialization : new Dictionary();
        }
        if ($request->propertiesToGroupBy?->isEmpty === false) {
            $request->resultType = FetchRequestResultType::dictionaryResultType;
        }
        return $request;
    }

    private function predicate(mixed $predicate): ?Predicate
    {
        if (!$predicate instanceof Dictionary) {
            return null;
        }
        $format = (string)$predicate["format"];
        if ($format === "") {
            return null;
        }
        /** @var ArrayClass<mixed> $arguments */
        $arguments = $predicate["arguments"] ?? new ArrayClass();
        return Predicate::format($format, $this->resolveVariables($arguments)) ?? fatal_error("Invalid predicate: \"$format\"");
    }

    /**
     * @return ArrayClass<SortDescriptor>|null
     */
    private function sortDescriptors(mixed $sortDescriptors): ?ArrayClass
    {
        if (!$sortDescriptors instanceof ArrayClass) {
            return null;
        }
        return $sortDescriptors->compactMap(fn(mixed $item): ?SortDescriptor => $item instanceof Dictionary && ($key = $item["key"]) ? new SortDescriptor((string)$key, (bool)($item["ascending"] ?? true)) : null);
    }

    /**
     * @return ArrayClass<ExpressionDescription|string>|null
     * @throws Exception
     */
    private function properties(mixed $properties, EntityDescription $entity): ?ArrayClass
    {
        if (!$properties instanceof ArrayClass) {
            return null;
        }
        return $properties->compactMap(fn(mixed $property): ExpressionDescription|string|null => $property instanceof Dictionary ? $this->expressionDescription($property, $entity) : (is_string($property) ? $property : null));
    }

    /**
     * @param Dictionary<mixed> $property
     * @throws Exception
     */
    private function expressionDescription(Dictionary $property, EntityDescription $entity): ?ExpressionDescription
    {
        $expression = $property["expression"];
        if (!$expression instanceof Dictionary || !($format = (string)$expression["format"])) {
            return null;
        }
        /** @var ArrayClass<mixed> $arguments */
        $arguments = $expression["arguments"] ?? new ArrayClass();
        $description = new ExpressionDescription();
        $description->entity = $entity;
        $description->name = (string)($property["name"] ?? fatal_error("A computed property requires a \"name\"."));
        $description->expression = Expression::expressionWithFormat($format, $this->resolveVariables($arguments));
        $description->resultType = AttributeType::tryFrom((int)$property["resultType"]) ?? AttributeType::double;
        return $description;
    }

    /**
     * @param ArrayClass<mixed> $arguments
     * @return ArrayClass<mixed>
     */
    private function resolveVariables(ArrayClass $arguments): ArrayClass
    {
        $variables = new AccessConditionResolver()->variables;
        $resolve = fn(mixed $value): mixed => is_string($value) && $variables[$value] !== null ? $variables[$value] : $value;
        return $arguments->map(fn(mixed $value): mixed => $value instanceof ArrayClass ? $value->map($resolve) : $resolve($value));
    }
}
