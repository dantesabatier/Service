<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use JsonException;
use Override;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ExpressionDescription;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Schema\AttributeSchema;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final class AggregateTool extends AbstractTool
{
    private const array inMemoryFunctions = ["median", "mode", "stddev"];
    private const array allowedFunctions = ["sum", "average", "min", "max", "count", "median", "mode", "stddev"];
    private const array expressionNames = ["sum" => "sum:", "average" => "average:", "min" => "min:", "max" => "max:", "count" => "count:", "median" => "median:", "mode" => "mode:", "stddev" => "stddev:"];
    private const array resultTypes = ["sum" => AttributeType::double, "average" => AttributeType::double, "min" => AttributeType::double, "max" => AttributeType::double, "count" => AttributeType::integer64];

    #[Override]
    public string $name {
        get => "aggregate";
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => [
                "entity" => ["type" => "string", "description" => "Always required. Entity name from the data model — call describe_model first if unsure."],
                "function" => ["type" => "string", "enum" => self::allowedFunctions],
                "property" => ["type" => "string"],
                "predicate" => ["type" => "string"],
                "arguments" => ["type" => "array"],
            ],
            "required" => ["entity", "function", "property"],
        ];
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws JsonException
     * @throws Exception
     */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        /** @var string $entity */
        $entity = $arguments["entity"] ?? fatal_error("entity required");
        /** @var string $function */
        $function = $arguments["function"] ?? fatal_error("function required");
        /** @var string $property */
        $property = $arguments["property"] ?? fatal_error("property required");
        $this->enforceEntityAuthorization($entity, AuthorizationType::read);
        in_array($function, self::allowedFunctions, true) ?: fatal_error("Invalid function");
        $this->validateKeyPath($entity, $property);
        $attribute = $this->entity($entity)->attributes[$property] ?? null;
        if ($attribute instanceof AttributeSchema && in_array($function, ["sum", "average"], true)) {
            in_array($attribute->type, ["integer", "float", "enum"], true) ?: fatal_error("Property must be numeric type \"$attribute->type\" given");
        }
        $request = $this->fetchRequest($entity);
        $predicate = $arguments["predicate"];
        $params = $arguments["arguments"] ?? new ArrayClass();
        if ($predicate) {
            $this->validatePredicateKeyPaths($entity, $predicate, $params);
            $request->predicate = $this->buildPredicate($predicate, $params);
        }
        $this->applySecurityScope($request);
        $result = in_array($function, self::inMemoryFunctions, true) ? $this->computeInMemory($request, $property, $function) : $this->computeDatabase($request, $property, $function);
        return $this->jsonResult(["entity" => $entity, "function" => $function, "property" => $property, "result" => round($result, 4)]);
    }

    /** @throws Exception */
    private function computeInMemory(mixed $request, string $property, string $function): float
    {
        $request->resultType = FetchRequestResultType::managedObjectResultType;
        /** @var ArrayClass<ManagedObject> $objects */
        $objects = $this->context->fetch($request);
        $values = $objects->map(fn(ManagedObject $object): float => (float)($object->valueForKeyPath($property) ?? 0.0));
        return Expression::expressionForFunction(self::expressionNames[$function], new ArrayClass([Expression::expressionForConstantValue($values)]))->expressionValue()?->floatValue ?? 0.0;
    }

    /** @throws Exception */
    private function computeDatabase(mixed $request, string $property, string $function): float
    {
        $description = new ExpressionDescription();
        $description->entity = $request->entity ?? fatal_error("Missing entity");
        $description->name = "value";
        $description->expression = Expression::expressionWithFormat(self::expressionNames[$function] . "(%K)", new ArrayClass([$property]));
        $description->resultType = self::resultTypes[$function];
        $request->propertiesToFetch = new ArrayClass([$description]);
        $request->resultType = FetchRequestResultType::dictionaryResultType;
        /** @var ArrayClass<Dictionary> $rows */
        $rows = $this->context->fetch($request);
        return (float)($rows[0]["value"] ?? 0.0);
    }
}
