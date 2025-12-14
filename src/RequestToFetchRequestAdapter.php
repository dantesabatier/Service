<?php

namespace Sabatier\Service;

use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\CompoundPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\URLQueryItem;
use function Sabatier\Foundation\string_is_equal;

/**
 * @psalm-import-type FetchRequestRepresentation from FetchRequestAdapter
 * @internal
 */
final class RequestToFetchRequestAdapter
{
    public FetchRequest $fetchRequest {
        get {
            $request = $this->request;
            $fetchRequest = new FetchRequest();
            $components = new URLComponents((string)$request->url);
            if ($queryItems = $components->queryItems) {
                if ($item = $queryItems->first(fn(URLQueryItem $item): bool => string_is_equal($item->name, "fetchRequest", CompareOptions::caseInsensitive))) {
                    if (($value = $item->value) && ($json = base64_decode($value)) && (json_validate($json))) {
                        /** @var FetchRequestRepresentation $fetchRequestRepresentation */
                        $fetchRequestRepresentation = json_decode($json);
                        $fetchRequest = new FetchRequestAdapter($fetchRequestRepresentation)->fetchRequest;
                    }
                } else {
                    $predicates = $queryItems->map(fn(URLQueryItem $item): ComparisonPredicate => new ComparisonPredicate(Expression::expressionForKeyPath($item->name), Expression::expressionForConstantValue($item->value)));
                    $fetchRequest->predicate = $predicates->count > 1 ? CompoundPredicate::andPredicateWithSubpredicates($predicates) : $predicates->first;
                }
            }
            if ($serialization = $request->serialization) {
                $fetchRequest->serialization = $serialization;
            }
            $fetchRequest->entity = $this->entity;
            return $fetchRequest;
        }
    }

    public function __construct(private readonly Request $request, private readonly EntityDescription $entity)
    {
    }
}
