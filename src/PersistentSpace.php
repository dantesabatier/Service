<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Sabatier\CoreData\AtomicStore;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\BatchFaultingArray;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ExpressionDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\CoreData\PersistentStore;
use Sabatier\CoreData\SQLEntity;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\CompoundPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\ExpressionType;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\URLQueryItem;
use function Sabatier\Foundation\string_is_equal;

class PersistentSpace extends Responder
{
    public EntityDescription $entity {
        get => $this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->valueForKey($this->request->url->lastPathComponent) ?? throw new NotFoundException("Unable to load entity \"{$this->request->url->lastPathComponent}\"");
    }
    public ?AtomicStore $atomicStore {
        get => $this->managedObjectContext->persistentStoreCoordinator?->persistentStores?->first(fn(PersistentStore $store): bool => $store instanceof AtomicStore);
    }
    public FetchRequest $fetchRequest {
        get {
            $request = $this->request;
            $fetchRequest = new FetchRequest();
            $components = new URLComponents((string)$request->url);
            if ($queryItems = $components->queryItems) {
                if ($item = $queryItems->first(fn(URLQueryItem $item): bool => string_is_equal($item->name, "fetchRequest", CompareOptions::caseInsensitive))) {
                    if (($value = $item->value) && ($json = base64_decode($value)) && (json_validate($json))) {
                        /** @var object{predicate: object{format: string, arguments?: array}, includesSubentities?: bool, fetchLimit?: int, fetchOffset?: int, fetchBatchSize?: int, sortDescriptors?: array, resultType: int, propertiesToFetch?: array, returnsDistinctResults?: bool, propertiesToGroupBy?: array, havingPredicate: object{format: string, arguments?: array}} $decoded */
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
                            $fetchRequest->sortDescriptors = new ArrayClass($decoded->sortDescriptors)->compactMap(fn(object $obj): ?SortDescriptor => property_exists($obj, "key") ? new SortDescriptor($obj->key, $obj->ascending) : null);
                        }
                        if (property_exists($decoded, "resultType")) {
                            $fetchRequest->resultType = FetchRequestResultType::from($decoded->resultType);
                        }
                        if (property_exists($decoded, "propertiesToFetch")) {
                            /** @psalm-suppress InvalidPropertyAssignmentValue */
                            $fetchRequest->propertiesToFetch = new ArrayClass($decoded->propertiesToFetch)->compactMap(function (mixed $element): ExpressionDescription|string|null {
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
            $fetchRequest->entity = $this->entity;
            if (($predicate = $fetchRequest->predicate) && ($store = $this->atomicStore)) {
                $fn = function (CompoundPredicate|ComparisonPredicate|Predicate $predicate) use ($store, &$fn): Predicate {
                    if ($predicate instanceof ComparisonPredicate) {
                        $expressions = new ArrayClass([$predicate->rightExpression, $predicate->leftExpression]);
                        if (($keyPathExpression = $expressions->first(fn(Expression $e): bool => $e->expressionType === ExpressionType::keyPath && str_ends_with($e->keyPath, SQLEntity::primaryKeyName))) && ($constantValueExpression = $expressions->first(fn(Expression $e): bool => !$e->isEqual($keyPathExpression)))) {
                            $expressionForConstantValue = Expression::expressionForConstantValue($store->objectID($this->entity, $constantValueExpression->constantValue));
                            $rightExpression = $keyPathExpression === $predicate->rightExpression ? $keyPathExpression : $expressionForConstantValue;
                            $leftExpression = $constantValueExpression === $predicate->leftExpression ? $expressionForConstantValue : $keyPathExpression;
                            return new ComparisonPredicate($rightExpression, $leftExpression, $predicate->predicateOperatorType, $predicate->comparisonPredicateModifier, $predicate->options);
                        }
                        return $predicate;
                    }
                    /** @psalm-suppress all */
                    return new CompoundPredicate($predicate->compoundPredicateType, $predicate->subpredicates->map(fn(CompoundPredicate|ComparisonPredicate $subpredicate): CompoundPredicate|ComparisonPredicate => $fn($subpredicate)));
                };
                $fetchRequest->predicate = $fn($predicate);
            }
            return $fetchRequest;
        }
    }
    public bool $isFirstResponder {
        get => $this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->offsetExists($this->request->url->lastPathComponent) ?? false;
    }
    public Response $response {
        get {
            $context = $this->managedObjectContext;
            $request = $this->request;
            switch ($request->httpMethod) {
                case HTTPRequestMethod::get:
                case HTTPRequestMethod::head:
                    $fetchRequest = $this->fetchRequest;
                    $fetchRequestResult = match ($fetchRequest->resultType) {
                        FetchRequestResultType::managedObjectResultType, FetchRequestResultType::managedObjectIDResultType,
                        FetchRequestResultType::dictionaryResultType => $context->fetch($fetchRequest),
                        FetchRequestResultType::countResultType => new Dictionary(["count" => $context->count($fetchRequest)])
                    };
                    /** @psalm-suppress TypeDoesNotContainType */
                    if ($fetchRequestResult instanceof BatchFaultingArray) {
                        return new BatchResponse($this, $fetchRequest, $fetchRequestResult);
                    }
                    if ($request->httpMethod === HTTPRequestMethod::get) {
                        $this->content = json_encode($fetchRequestResult, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
                        $this->headerFields["Content-Type"] = "application/json";
                    }
                    break;
                case HTTPRequestMethod::post:
                case HTTPRequestMethod::put:
                case HTTPRequestMethod::patch:
                case HTTPRequestMethod::delete:
                    if ($request->httpMethod !== HTTPRequestMethod::delete) {
                        $contentType = $request->valueForHttpHeaderField("Content-Type") ?? "text/plain";
                        $mediaType = $contentType;
                        if (str_contains($contentType, ";")) {
                            [$mediaType,] = explode(";", $contentType);
                        }
                        $supportedMediaTypes = new ArrayClass(["application/x-www-form-urlencoded", "multipart/form-data", "application/json"]);
                        if (!$supportedMediaTypes->contains(fn(string $supportedMediaType): bool => string_is_equal($supportedMediaType, $mediaType, CompareOptions::caseInsensitive))) {
                            throw new UnsupportedMediaTypeException();
                        }
                    }
                    $body = $request->getParsedBody();
                    if ($request->httpMethod !== HTTPRequestMethod::post && !isset($body[SQLEntity::primaryKeyName])) {
                        $components = new URLComponents($request->url->absoluteString);
                        if ($item = $components->queryItems?->first(fn(URLQueryItem $item): bool => $item->name === SQLEntity::primaryKeyName)) {
                            $body[$item->name] = (int)$item->value;
                        }
                    }
                    $managedObject = function (ManagedObjectID|int $objectID): ?ManagedObject {
                        /** @var FetchRequest<ManagedObject> $fetchRequest */
                        $fetchRequest = new FetchRequest();
                        $fetchRequest->entity = $this->entity;
                        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath(SQLEntity::primaryKeyName), Expression::expressionForConstantValue($objectID));
                        if ($serialization = $this->serialization) {
                            $fetchRequest->serialization = $serialization;
                        }
                        return $this->managedObjectContext->fetch($fetchRequest)->first;
                    };
                    $objectID = $body[SQLEntity::primaryKeyName] ?? null;
                    if ($objectID === null) {
                        if ($request->httpMethod !== HTTPRequestMethod::post) {
                            throw new BadRequestException(sprintf("\"%s\" can not be null", SQLEntity::primaryKeyName));
                        }
                        $object = null;
                    } else {
                        if ($store = $this->atomicStore) {
                            $objectID = $store->objectID($this->entity, $objectID);
                        }
                        $object = $managedObject($objectID);
                    }
                    if (!$object instanceof ManagedObject) {
                        if ($request->httpMethod !== HTTPRequestMethod::post) {
                            throw new NotFoundException();
                        }
                    } elseif ($request->httpMethod === HTTPRequestMethod::post) {
                        throw new ConflictException();
                    }
                    if ($request->httpMethod === HTTPRequestMethod::delete) {
                        $this->statusCode = HTTPStatusCode::noContent;
                        /** @psalm-suppress PossiblyNullArgument */
                        $context->delete($object);
                        $context->save();
                    } else {
                        $object ??= EntityDescription::insertNewObject($this->entity->name, $context);
                        $object->setValuesForKeys(Dictionary::dictionaryWithArray($body));
                        $context->save();
                        $object = $managedObject($object->objectID);
                        $this->content = json_encode($object?->serialized($this->serialization), JSON_PRESERVE_ZERO_FRACTION);
                        $this->headerFields["Content-Type"] = "application/json";
                    }
                    break;
                case HTTPRequestMethod::options:
                    break;
                default:
                    throw new MethodNotAllowedException();
            }
            return new Response($this);
        }
    }
}
