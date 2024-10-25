<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\AtomicStore;
use Sabatier\CoreData\BatchFaultingArray;
use Sabatier\CoreData\EntityDescription;
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
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\CompoundPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\ExpressionType;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\URLQueryItem;
use function Sabatier\Foundation\string_is_equal;

class PersistentSpace extends Responder
{
    private readonly ?AtomicStore $atomicStore;
    private readonly EntityDescription $entity;

    public function __construct()
    {
        parent::__construct();
        unset($this->entity);
        unset($this->atomicStore);
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function __get(string $name)
    {
        if ($name === "atomicStore") {
            /** @psalm-suppress PropertyTypeCoercion */
            $this->$name = $this->managedObjectContext->persistentStoreCoordinator?->persistentStores?->first(fn(PersistentStore $store): bool => $store instanceof AtomicStore);
            return $this->$name;
        }
        if ($name === "entity") {
            $this->$name = $this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->valueForKey($this->request->url->lastPathComponent) ?? throw new NotFoundException("Unable to load entity \"{$this->request->url->lastPathComponent}\"");
            return $this->$name;
        }
        return parent::__get($name);
    }

    #[Override]
    public function isFirstResponder(): bool
    {
        return $this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->offsetExists($this->request->url->lastPathComponent) ?? false;
    }

    #[Override]
    public function response(): HTTPURLResponse
    {
        $context = $this->managedObjectContext;
        $request = $this->request;
        switch ($request->httpMethod) {
            case HTTPRequestMethod::get:
            case HTTPRequestMethod::head:
                $fetchRequest = $this->fetchRequest;
                $fetchRequest->entity = $this->entity;
                if (($predicate = $fetchRequest->predicate) && ($store = $this->atomicStore)) {
                    $fn = function (CompoundPredicate|ComparisonPredicate|Predicate $predicate) use ($store, &$fn): Predicate {
                        if ($predicate instanceof ComparisonPredicate) {
                            $expressions = new ArrayClass([$predicate->rightExpression, $predicate->leftExpression]);
                            if (($keyPathExpression = $expressions->first(fn(Expression $e): bool => $e->expressionType === ExpressionType::keyPath && str_ends_with($e->keyPath(), SQLEntity::primaryKeyName))) && ($constantValueExpression = $expressions->first(fn(Expression $e): bool => !$e->isEqual($keyPathExpression)))) {
                                $expressionForConstantValue = Expression::expressionForConstantValue($store->objectID($this->entity, $constantValueExpression->constantValue()));
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
                $fetchRequestResult = match ($fetchRequest->resultType) {
                    FetchRequestResultType::managedObjectResultType, FetchRequestResultType::managedObjectIDResultType,
                    FetchRequestResultType::dictionaryResultType => $context->fetch($fetchRequest),
                    FetchRequestResultType::countResultType => new Dictionary(["count" => $context->count($fetchRequest)])
                };
                /** @psalm-suppress TypeDoesNotContainType */
                if ($fetchRequestResult instanceof BatchFaultingArray) {
                    return new BatchResponse($request->url, $fetchRequestResult, $fetchRequest);
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
                    $components = new URLComponents($this->request->url->absoluteString);
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
        return new HTTPURLResponse($request->url, $this->statusCode, headerFields: $this->headerFields);
    }
}
