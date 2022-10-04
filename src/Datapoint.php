<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\BatchFaultingArray;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ExpressionDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\ComparisonPredicate;
use Sabatier\Foundation\CompoundPredicate;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Expression;
use Sabatier\Foundation\HTTPRequestMethod;
use Sabatier\Foundation\HTTPStatusCode;
use Sabatier\Foundation\HTTPURLResponse;
use Sabatier\Foundation\Predicate;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\URLQueryItem;

use function Sabatier\Foundation\string_begins_with;
use function Sabatier\Foundation\string_is_equal;

/** @internal */
class Datapoint extends Endpoint
{
    public function __construct(Service $service, public readonly EntityDescription $entity)
    {
        parent::__construct($service);
    }

    public function name(): string
    {
        return $this->entity->name;
    }

    public function response(): HTTPURLResponse
    {
        $service = $this->service;
        $request = $service->request;
        $serialization = $service->serialization;
        $context = $service->persistentContainer->viewContext;
        $context->transactionAuthor = $service->authorization->credential?->user;
        switch ($request->httpMethod) {
            case HTTPRequestMethod::get:
                $fetchRequest = $this->fetchRequest();
                $fetchRequestResult = match ($fetchRequest->resultType) {
                    FetchRequestResultType::managedObjectResultType, FetchRequestResultType::managedObjectIDResultType,
                    FetchRequestResultType::dictionaryResultType => $context->fetch($fetchRequest),
                    FetchRequestResultType::countResultType => new Dictionary(['count' => $context->count($fetchRequest)])
                };
                if ($fetchRequestResult instanceof BatchFaultingArray) {
                    return new BatchResponse($request->url, $fetchRequestResult, $fetchRequest);
                }
                $this->content = json_encode($fetchRequestResult, JSON_PRESERVE_ZERO_FRACTION);
                return new HTTPURLResponse($request->url, HTTPStatusCode::ok, null, new Dictionary(['Content-Type' => "application/json; charset=utf-8"]));
            case HTTPRequestMethod::post:
            case HTTPRequestMethod::put:
            case HTTPRequestMethod::patch:
            case HTTPRequestMethod::delete:
                $body = $request->httpBody ?? '';
                if (!($array = json_decode($body, true))) {
                    $components = new URLComponents($request->url->absoluteString);
                    $items = $components->queryItems ?? throw new BadRequestException();
                    $array = $items->reduce([], function (array &$result, URLQueryItem $item): array {
                        $result[$item->name] = $item->value;
                        return $result;
                    });
                }
                $object = null;
                $entity = $this->entity;
                /** @var Dictionary<mixed> $dictionary */
                $dictionary = Dictionary::dictionaryWithArray($array);
                $objectID = $dictionary['objectID'];
                if ($objectID === null) {
                    if ($request->httpMethod != HTTPRequestMethod::post) {
                        throw new BadRequestException();
                    }
                } else {
                    /** @var FetchRequest<ManagedObject> $fetchRequest */
                    $fetchRequest = new FetchRequest();
                    $fetchRequest->entity = $entity;
                    $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath('objectID'), Expression::expressionForConstantValue($objectID));
                    if ($serialization) {
                        $fetchRequest->serialization = $serialization;
                    }
                    $object = $context->fetch($fetchRequest)->first();
                }
                if ($object === null) {
                    if ($request->httpMethod != HTTPRequestMethod::post) {
                        throw new NotFoundException();
                    }
                } else {
                    if ($request->httpMethod == HTTPRequestMethod::post) {
                        throw new ConflictException();
                    }
                }
                if ($request->httpMethod == HTTPRequestMethod::delete) {
                    /** @psalm-suppress PossiblyNullArgument */
                    $context->delete($object);
                    $context->save();
                    return new HTTPURLResponse($request->url, HTTPStatusCode::noContent);
                } else {
                    /** @noinspection SpellCheckingInspection */
                    if (($password = $dictionary['password']) && !string_begins_with($password, '\$2[abxy]', CompareOptions::quoted)) {
                        $dictionary['password'] = password_hash($password, PASSWORD_BCRYPT);
                    }
                    $object ??= EntityDescription::insertNewObject($entity->name, $context);
                    $object->setValuesForKeys($dictionary);
                    $context->save();
                    $this->content = json_encode($object->serialized($serialization), JSON_PRESERVE_ZERO_FRACTION);
                    return new HTTPURLResponse($request->url, HTTPStatusCode::ok, null, new Dictionary(['Content-Type' => "application/json; charset=utf-8"]));
                }
            default:
                throw new MethodNotAllowedException();
        }
    }

    /** @throws Exception */
    private function fetchRequest(): FetchRequest
    {
        $service = $this->service;
        $request = $service->request;
        $fetchRequest = new FetchRequest();
        $fetchRequest->entity = $this->entity;
        $components = new URLComponents($request->url->absoluteString);
        if ($items = $components->queryItems) {
            if ($item = $items->first(fn(URLQueryItem $item): bool => string_is_equal($item->name, 'fetchRequest', CompareOptions::caseInsensitive))) {
                if (($value = $item->value) && ($json = base64_decode($value)) && ($decoded = json_decode($json))) {
                    if (property_exists($decoded, 'predicate')) {
                        $predicate = $decoded->predicate;
                        if (property_exists($predicate, 'format')) {
                            $fetchRequest->predicate = Predicate::format($predicate->format, ArrayClass::arrayWithArray($predicate->arguments ?? []));
                        }
                    }
                    $fetchRequest->includesSubentities = $decoded->includesSubentities ?? true;
                    $fetchRequest->fetchLimit = $decoded->fetchLimit ?? 0;
                    $fetchRequest->fetchOffset = $decoded->fetchOffset ?? 0;
                    $fetchRequest->fetchBatchSize = $decoded->fetchBatchSize ?? 0;
                    if (property_exists($decoded, 'sortDescriptors')) {
                        $fetchRequest->sortDescriptors = (new ArrayClass($decoded->sortDescriptors))->map(fn(object $obj): SortDescriptor => new SortDescriptor($obj->key, $obj->ascending));
                    }
                    if (property_exists($decoded, 'resultType')) {
                        $fetchRequest->resultType = FetchRequestResultType::from($decoded->resultType);
                    }
                    if (property_exists($decoded, 'propertiesToFetch')) {
                        /** @psalm-suppress InvalidPropertyAssignmentValue */
                        $fetchRequest->propertiesToFetch = (new ArrayClass($decoded->propertiesToFetch))->compactMap(function (mixed $element): ExpressionDescription|string|null {
 // @phpstan-ignore-line
                            if (is_string($element)) {
                                return $element;
                            } elseif (is_object($element)) {
                                if (property_exists($element, 'name') && property_exists($element, 'expression')) {
                                    $expression = $element->expression;
                                    if (property_exists($expression, 'format')) {
                                        $expressionDescription = new ExpressionDescription();
                                        $expressionDescription->name = $element->name;
                                        $expressionDescription->expression = Expression::expressionWithFormat($expression->format, ArrayClass::arrayWithArray($expression->arguments ?? []));
                                        if (property_exists($expression, 'expressionResultType')) {
                                            $expressionDescription->expressionResultType = AttributeType::from($expression->expressionResultType);
                                        }
                                        return $expressionDescription;
                                    }
                                }
                            }
                            return null;
                        });
                    }
                    $fetchRequest->returnsDistinctResults = $decoded->returnsDistinctResults ?? false;
                    if (property_exists($decoded, 'propertiesToGroupBy')) {
                        $fetchRequest->propertiesToGroupBy = new ArrayClass($decoded->propertiesToGroupBy);
                    }
                    if (property_exists($decoded, 'havingPredicate')) {
                        $havingPredicate = $decoded->havingPredicate;
                        if (property_exists($havingPredicate, 'format')) {
                            $fetchRequest->havingPredicate = Predicate::format($havingPredicate->format, ArrayClass::arrayWithArray($havingPredicate->arguments ?? []));
                        }
                    }
                }
            } else {
                $predicates = $items->map(fn(URLQueryItem $item): ComparisonPredicate => new ComparisonPredicate(Expression::expressionForKeyPath($item->name), Expression::expressionForConstantValue($item->value)));
                /** @psalm-suppress InvalidArgument */
                $fetchRequest->predicate = $predicates->count() > 1 ? CompoundPredicate::andPredicateWithSubpredicates($predicates) : $predicates->first();
            }
        }
        if ($serialization = $service->serialization) {
            $fetchRequest->serialization = $serialization;
        }
        return $fetchRequest;
    }
}
