<?php

namespace Sabatier\Service;

use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ExpressionDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\SortDescriptor;

/**
 * @phpstan-type FetchRequestRepresentation object{predicate: object{format: string, arguments?: array}, includesSubentities?: bool, fetchLimit?: int, fetchOffset?: int, fetchBatchSize?: int, sortDescriptors?: object{key: string, ascending: bool}[], resultType: int, propertiesToFetch?: array, returnsDistinctResults?: bool, propertiesToGroupBy?: array, havingPredicate: object{format: string, arguments?: array}}
 * @internal
 */
final class FetchRequestAdapter
{
    public FetchRequest $fetchRequest {
        get {
            $fetchRequest = new FetchRequest();
            $fetchRequestRepresentation = $this->fetchRequestRepresentation;
            if (isset($fetchRequestRepresentation->predicate) && is_object($fetchRequestRepresentation->predicate)) {
                $predicate = $fetchRequestRepresentation->predicate;
                if (isset($predicate->format)) {
                    $fetchRequest->predicate = Predicate::format($predicate->format, ArrayClass::arrayWithArray($predicate->arguments ?? []));
                }
            }
            $fetchRequest->includesSubentities = $fetchRequestRepresentation->includesSubentities ?? true;
            $fetchRequest->fetchLimit = $fetchRequestRepresentation->fetchLimit ?? 0;
            $fetchRequest->fetchOffset = $fetchRequestRepresentation->fetchOffset ?? 0;
            $fetchRequest->fetchBatchSize = $fetchRequestRepresentation->fetchBatchSize ?? 0;
            if (isset($fetchRequestRepresentation->sortDescriptors) && is_array($fetchRequestRepresentation->sortDescriptors)) {
                $fetchRequest->sortDescriptors = new ArrayClass($fetchRequestRepresentation->sortDescriptors)->compactMap(function (object $obj): ?SortDescriptor {
                    if (!isset($obj->key)) {
                        return null;
                    }
                    return new SortDescriptor($obj->key, $obj->ascending ?? true);
                });
            }
            if (isset($fetchRequestRepresentation->resultType)) {
                $fetchRequest->resultType = FetchRequestResultType::from($fetchRequestRepresentation->resultType);
            }
            $propertyTransform = static function (mixed $element): ExpressionDescription|string|null {
                if (is_string($element)) {
                    return $element;
                }
                if (!is_object($element)) {
                    return null;
                }
                if (!isset($element->name) || !isset($element->expression) || !is_object($element->expression)) {
                    return null;
                }
                $expression = $element->expression;
                if (!isset($expression->format)) {
                    return null;
                }
                $desc = new ExpressionDescription();
                $desc->name = $element->name;
                $desc->expression = Expression::expressionWithFormat($expression->format, ArrayClass::arrayWithArray($expression->arguments ?? []));
                if (isset($element->resultType)) {
                    $desc->resultType = AttributeType::from($element->resultType);
                }
                return $desc;
            };
            if (isset($fetchRequestRepresentation->propertiesToFetch) && is_array($fetchRequestRepresentation->propertiesToFetch)) {
                $fetchRequest->propertiesToFetch = new ArrayClass($fetchRequestRepresentation->propertiesToFetch)->compactMap($propertyTransform);
            }
            $fetchRequest->returnsDistinctResults = $fetchRequestRepresentation->returnsDistinctResults ?? false;
            if (isset($fetchRequestRepresentation->propertiesToGroupBy) && is_array($fetchRequestRepresentation->propertiesToGroupBy)) {
                $fetchRequest->propertiesToGroupBy = new ArrayClass($fetchRequestRepresentation->propertiesToGroupBy)->compactMap($propertyTransform);
            }
            if (isset($fetchRequestRepresentation->havingPredicate) && is_object($fetchRequestRepresentation->havingPredicate)) {
                $havingPredicate = $fetchRequestRepresentation->havingPredicate;
                if (isset($havingPredicate->format)) {
                    $fetchRequest->havingPredicate = Predicate::format($havingPredicate->format, ArrayClass::arrayWithArray($havingPredicate->arguments ?? []));
                }
            }
            return $fetchRequest;
        }
    }

    /**
     * @param FetchRequestRepresentation $fetchRequestRepresentation
     */
    public function __construct(public readonly object $fetchRequestRepresentation)
    {
    }
}
