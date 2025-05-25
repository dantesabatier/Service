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
                        /** @var object{predicate: object{format: string, arguments?: array}, includesSubentities?: bool, fetchLimit?: int, fetchOffset?: int, fetchBatchSize?: int, sortDescriptors?: object{key: string, ascending: bool}[], resultType: int, propertiesToFetch?: array, returnsDistinctResults?: bool, propertiesToGroupBy?: array, havingPredicate: object{format: string, arguments?: array}} $decoded */
                        $decoded = json_decode($json);
                        if (isset($decoded->predicate)) {
                            $predicate = $decoded->predicate;
                            if (isset($predicate->format)) {
                                $fetchRequest->predicate = Predicate::format($predicate->format, ArrayClass::arrayWithArray($predicate->arguments ?? []));
                            }
                        }
                        $fetchRequest->includesSubentities = $decoded->includesSubentities ?? true;
                        $fetchRequest->fetchLimit = $decoded->fetchLimit ?? 0;
                        $fetchRequest->fetchOffset = $decoded->fetchOffset ?? 0;
                        $fetchRequest->fetchBatchSize = $decoded->fetchBatchSize ?? 0;
                        if (isset($decoded->sortDescriptors)) {
                            $fetchRequest->sortDescriptors = new ArrayClass($decoded->sortDescriptors)->compactMap(
                            /**
                             * @param object{key: string, ascending?: bool} $obj
                             * @return SortDescriptor|null
                             */
                                fn(object $obj): ?SortDescriptor => isset($obj->key) ? new SortDescriptor($obj->key, $obj->ascending ?? true) : null);
                        }
                        if (isset($decoded->resultType)) {
                            $fetchRequest->resultType = FetchRequestResultType::from($decoded->resultType);
                        }
                        if (isset($decoded->propertiesToFetch)) {
                            $fetchRequest->propertiesToFetch = new ArrayClass($decoded->propertiesToFetch)->compactMap(
                            /**
                             * @param string|object{name: string, expression: object{format: string}, resultType: int} $element
                             * @return ExpressionDescription|string|null
                             */
                                function (mixed $element): ExpressionDescription|string|null {
                                    if (is_string($element)) {
                                        return $element;
                                    }
                                    if (!is_object($element)) {
                                        return null;
                                    }
                                    if (!isset($element->name)) {
                                        return null;
                                    }
                                    if (!isset($element->expression)) {
                                        return null;
                                    }
                                    $expression = $element->expression;
                                    if (!isset($expression->format)) {
                                        return null;
                                    }
                                    $expressionDescription = new ExpressionDescription();
                                    $expressionDescription->name = $element->name;
                                    $expressionDescription->expression = Expression::expressionWithFormat($expression->format, ArrayClass::arrayWithArray($expression->arguments ?? []));
                                    if (isset($element->resultType)) {
                                        $expressionDescription->resultType = AttributeType::from($element->resultType);
                                    }
                                    return $expressionDescription;
                                });
                        }
                        $fetchRequest->returnsDistinctResults = $decoded->returnsDistinctResults ?? false;
                        if (isset($decoded->propertiesToGroupBy)) {
                            $fetchRequest->propertiesToGroupBy = new ArrayClass($decoded->propertiesToGroupBy);
                        }
                        if (isset($decoded->havingPredicate)) {
                            $havingPredicate = $decoded->havingPredicate;
                            if (isset($havingPredicate->format)) {
                                $fetchRequest->havingPredicate = Predicate::format($havingPredicate->format, ArrayClass::arrayWithArray($havingPredicate->arguments ?? []));
                            }
                        }
                    }
                } else {
                    $predicates = $queryItems->map(fn(URLQueryItem $item): ComparisonPredicate => new ComparisonPredicate(Expression::expressionForKeyPath($item->name), Expression::expressionForConstantValue($item->value)));
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
