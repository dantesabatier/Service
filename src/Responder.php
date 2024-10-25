<?php

namespace Sabatier\Service;

use Exception;
use JetBrains\PhpStorm\ExpectedValues;
use ReflectionClass;
use ReflectionMethod;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ExpressionDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\CompoundPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\URLQueryItem;
use function Sabatier\Foundation\string_is_equal;
use function Sabatier\Foundation\url_validate;

/**
 * An abstract interface for responding to and handling url requests.
 * @psalm-consistent-constructor
 */
abstract class Responder extends ObjectClass
{
    public readonly URLRequest $request;
    /** @var Dictionary<mixed>|null */
    public readonly ?Dictionary $serialization;
    /** @var Dictionary<mixed> */
    public Dictionary $headerFields;
    public readonly FetchRequest $fetchRequest;
    public readonly ManagedObjectContext $managedObjectContext;
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods;
    public bool $isProtectedContentAvailable = false;
    #[ExpectedValues(valuesFromClass: HTTPStatusCode::class)]
    public int $statusCode = HTTPStatusCode::ok;
    public ?string $content = null;
    public readonly ?string $selector;
    public readonly bool $isEndpoint;
    public readonly bool $isActionable;

    public function __construct()
    {
        unset($this->request);
        unset($this->serialization);
        unset($this->headerFields);
        unset($this->fetchRequest);
        unset($this->managedObjectContext);
        unset($this->allowedMethods);
        unset($this->selector);
        unset($this->isEndpoint);
        unset($this->isActionable);
    }

    /**
     * @throws Exception
     */
    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            "request" => Application::shared()->request,
            "serialization" => $this->serialization(),
            "headerFields" => $this->headerFields(),
            "fetchRequest" => $this->fetchRequest(),
            "managedObjectContext" => Application::shared()->persistentContainer->viewContext,
            "allowedMethods" => new ArrayClass([HTTPRequestMethod::head, HTTPRequestMethod::options, HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::patch, HTTPRequestMethod::put, HTTPRequestMethod::delete]),
            "selector" => $this->selector(),
            "isEndpoint" => $this->isEndpoint(),
            "isActionable" => $this->selector !== null,
            default => $this->valueForUndefinedKey($name)
        };
    }

    private function serialization(): ?Dictionary
    {
        if (!($string = $this->request->valueForHttpHeaderField("serialization")) || !json_validate($string) || !($array = json_decode($string, true))) {
            return null;
        }
        return Dictionary::dictionaryWithArray($array);
    }

    private function headerFields(): Dictionary
    {
        /** @var Dictionary<mixed> $headerFields */
        $headerFields = new Dictionary();
        if ($origin = $this->request->valueForHttpHeaderField("Origin")) {
            $headerFields["Access-Control-Allow-Origin"] = $origin;
            $headerFields["Access-Control-Allow-Credentials"] = true;
            $headerFields["Vary"] = "Origin";
        }
        if ($value = $this->request->valueForHttpHeaderField("Access-Control-Request-Method")) {
            $headerFields["Access-Control-Allow-Methods"] = $value;
        }
        if ($value = $this->request->valueForHttpHeaderField("Access-Control-Request-Headers")) {
            $headerFields["Access-Control-Allow-Headers"] = $value;
        }
        return $headerFields;
    }

    private function fetchRequest(): FetchRequest
    {
        $fetchRequest = new FetchRequest();
        $components = new URLComponents((string)$this->request->url);
        if ($queryItems = $components->queryItems) {
            if ($item = $queryItems->first(fn(URLQueryItem $item): bool => string_is_equal($item->name, "fetchRequest", CompareOptions::caseInsensitive))) {
                if (($value = $item->value) && ($json = base64_decode($value)) && (json_validate($json))) {
                    $decoded = json_decode($json);
                    if (property_exists($decoded, "predicate")) {
                        $predicate = $decoded->predicate;
                        if (property_exists($predicate, "format")) {
                            $fetchRequest->predicate = Predicate::format($predicate->format, ArrayClass::arrayWithArray($predicate->arguments ?? []));
                        }
                    }
                    $fetchRequest->includesSubentities = $decoded->includesSubentities ?? true;
                    $fetchRequest->fetchLimit = $decoded->fetchLimit ?? 0;
                    $fetchRequest->fetchOffset = $decoded->fetchOffset ?? 0;
                    $fetchRequest->fetchBatchSize = $decoded->fetchBatchSize ?? 0;
                    if (property_exists($decoded, "sortDescriptors")) {
                        /** @psalm-suppress InvalidPropertyAssignmentValue */
                        $fetchRequest->sortDescriptors = (new ArrayClass($decoded->sortDescriptors))->compactMap(fn(object $obj): ?SortDescriptor => property_exists($obj, "key") ? new SortDescriptor($obj->key, $obj->ascending) : null);
                    }
                    if (property_exists($decoded, "resultType")) {
                        $fetchRequest->resultType = FetchRequestResultType::from($decoded->resultType);
                    }
                    if (property_exists($decoded, "propertiesToFetch")) {
                        /** @psalm-suppress InvalidPropertyAssignmentValue */
                        $fetchRequest->propertiesToFetch = (new ArrayClass($decoded->propertiesToFetch))->compactMap(function (mixed $element): ExpressionDescription|string|null {
                            if (is_string($element)) {
                                return $element;
                            }
                            if (is_object($element) && (property_exists($element, "name") && property_exists($element, "expression"))) {
                                $expression = $element->expression;
                                if (property_exists($expression, "format")) {
                                    $expressionDescription = new ExpressionDescription();
                                    $expressionDescription->name = $element->name;
                                    $expressionDescription->expression = Expression::expressionWithFormat($expression->format, ArrayClass::arrayWithArray($expression->arguments ?? []));
                                    if (property_exists($element, "resultType")) {
                                        $expressionDescription->resultType = AttributeType::from($element->resultType);
                                    }
                                    return $expressionDescription;
                                }
                            }
                            return null;
                        });
                    }
                    $fetchRequest->returnsDistinctResults = $decoded->returnsDistinctResults ?? false;
                    if (property_exists($decoded, "propertiesToGroupBy")) {
                        $fetchRequest->propertiesToGroupBy = new ArrayClass($decoded->propertiesToGroupBy);
                    }
                    if (property_exists($decoded, "havingPredicate")) {
                        $havingPredicate = $decoded->havingPredicate;
                        if (property_exists($havingPredicate, "format")) {
                            $fetchRequest->havingPredicate = Predicate::format($havingPredicate->format, ArrayClass::arrayWithArray($havingPredicate->arguments ?? []));
                        }
                    }
                }
            } else {
                $predicates = $queryItems->map(fn(URLQueryItem $item): ComparisonPredicate => new ComparisonPredicate(Expression::expressionForKeyPath($item->name), Expression::expressionForConstantValue($item->value)));
                /** @psalm-suppress InvalidArgument */
                $fetchRequest->predicate = $predicates->count > 1 ? CompoundPredicate::andPredicateWithSubpredicates($predicates) : $predicates->first;
            }
        }
        if ($serialization = $this->serialization) {
            $fetchRequest->serialization = $serialization;
        }
        return $fetchRequest;
    }

    private function isEndpoint(): bool
    {
        $path = $this->request->url->path;
        $reflectionClass = new ReflectionClass($this);
        foreach ($reflectionClass->getAttributes(Endpoint::class) as $attribute) {
            $endpoint = $attribute->newInstance();
            if (string_is_equal($path, $endpoint->path ?? "/{$reflectionClass->getShortName()}", CompareOptions::caseInsensitive)) {
                return true;
            }
        }
        return false;
    }

    private function selector(): ?string
    {
        $path = $this->request->url->path;
        $reflectionClass = new ReflectionClass($this);
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $selector = $method->name;
            foreach ($method->getAttributes(Action::class) as $attribute) {
                $action = $attribute->newInstance();
                $other = $action->path ?? "/$selector";
                if (url_validate($other)) {
                    $components = new URLComponents($other);
                    $other = "$components->path$components->query";
                }
                if (string_is_equal($path, $other, CompareOptions::caseInsensitive)) {
                    return $selector;
                }
            }
        }
        return null;
    }

    /**
     * Returns a Boolean value indicating whether this object is the first responder.
     * @return bool true if the responder is the first responder; otherwise, false.
     */
    public function isFirstResponder(): bool
    {
        return $this->allowedMethods->containsElement($this->request->httpMethod) && ($this->isEndpoint || $this->isActionable);
    }

    /**
     * @throws Exception
     */
    public function response(): HTTPURLResponse
    {
        if (match ($this->request->httpMethod) {
                HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::patch, HTTPRequestMethod::delete => true,
                default => false,
            } && ($selector = $this->selector)) {
            $this->perform($selector);
            if ($this->request->httpMethod === HTTPRequestMethod::delete) {
                $this->statusCode = HTTPStatusCode::noContent;
            }
        }
        return new HTTPURLResponse($this->request->url, $this->statusCode, headerFields: $this->headerFields);
    }
}
