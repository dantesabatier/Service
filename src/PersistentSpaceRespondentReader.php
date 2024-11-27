<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\BatchFaultingArray;
use Sabatier\CoreData\ExpressionDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\CompoundPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\URLQueryItem;
use function Sabatier\Foundation\string_is_equal;

/** @internal */
class PersistentSpaceRespondentReader extends PersistentSpaceRespondent
{
    public FetchRequest $fetchRequest {
        get {
            /** @var PersistentSpace $responder */
            $responder = $this->responder;
            $request = $responder->request;
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
            if ($serialization = $request->serialization) {
                $fetchRequest->serialization = $serialization;
            }
            $fetchRequest->entity = $responder->entity;
            return $fetchRequest;
        }
    }
    public Response $response {
        get {
            $responder = $this->responder;
            $context = $responder->managedObjectContext;
            $fetchRequest = $this->fetchRequest;
            $fetchRequestResult = match ($fetchRequest->resultType) {
                FetchRequestResultType::managedObjectResultType, FetchRequestResultType::managedObjectIDResultType,
                FetchRequestResultType::dictionaryResultType => $context->fetch($fetchRequest),
                FetchRequestResultType::countResultType => new Dictionary(["count" => $context->count($fetchRequest)])
            };
            /** @psalm-suppress TypeDoesNotContainType */
            if ($fetchRequestResult instanceof BatchFaultingArray) {
                return new BatchResponse($responder, $fetchRequest, $fetchRequestResult);
            }
            $responder->content = json_encode($fetchRequestResult, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
            $responder->headerFields["Content-Type"] = "application/json";
            return new Response($responder);
        }
    }
}
