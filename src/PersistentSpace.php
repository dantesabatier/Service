<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\AtomicStore;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\BatchFaultingArray;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ExpressionDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\PersistentStore;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\CompoundPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\URLQueryItem;
use function Sabatier\Foundation\string_is_equal;
use const Sabatier\CoreData\XMLStoreType;

/** @internal */
class PersistentSpace extends Responder
{
    private readonly EntityDescription $entity;
    private readonly FetchRequest $fetchRequest;

    public function __construct()
    {
        parent::__construct();
        unset($this->fetchRequest);
        unset($this->entity);
    }

    /**
     * @throws Exception
     */
    public function __get(string $name)
    {
        if ($name == "entity") {
            $this->$name = $this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->valueForKey($this->request->url->lastPathComponent) ?? throw new NotFoundException("Unable to load entity \"{$this->request->url->lastPathComponent}\"");
            return $this->$name;
        } elseif ($name == "fetchRequest") {
            $fetchRequest = new FetchRequest();
            $fetchRequest->entity = $this->entity;
            $components = new URLComponents($this->request->url->absoluteString);
            if ($queryItems = $components->queryItems) {
                if ($item = $queryItems->first(fn(URLQueryItem $item): bool => string_is_equal($item->name, "fetchRequest", CompareOptions::caseInsensitive))) {
                    if (($value = $item->value) && ($json = base64_decode($value)) && ($decoded = json_decode($json, null, 512, JSON_THROW_ON_ERROR))) {
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
                            $fetchRequest->sortDescriptors = (new ArrayClass($decoded->sortDescriptors))->map(fn(object $obj): SortDescriptor => new SortDescriptor($obj->key, $obj->ascending));
                        }
                        if (property_exists($decoded, "resultType")) {
                            $fetchRequest->resultType = FetchRequestResultType::from($decoded->resultType);
                        }
                        if (property_exists($decoded, "propertiesToFetch")) {
                            /** @psalm-suppress InvalidPropertyAssignmentValue */
                            $fetchRequest->propertiesToFetch = (new ArrayClass($decoded->propertiesToFetch))->compactMap(function (mixed $element): ExpressionDescription|string|null {
                                    if (is_string($element)) {
                                        return $element;
                                    } elseif (is_object($element)) {
                                        if (property_exists($element, "name") && property_exists($element, "expression")) {
                                            $expression = $element->expression;
                                            if (property_exists($expression, "format")) {
                                                $expressionDescription = new ExpressionDescription();
                                                $expressionDescription->name = $element->name;
                                                $expressionDescription->expression = Expression::expressionWithFormat($expression->format, ArrayClass::arrayWithArray($expression->arguments ?? []));
                                                if (property_exists($expression, "expressionResultType")) {
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
                    $fetchRequest->predicate = $predicates->count() > 1 ? CompoundPredicate::andPredicateWithSubpredicates($predicates) : $predicates->first();
                }
            }
            if ($serialization = $this->serialization) {
                $fetchRequest->serialization = $serialization;
            }
            $this->$name = $fetchRequest;
            return $this->$name;
        } else {
            return parent::__get($name);
        }
    }

    public function isFirstResponder(): bool
    {
        return $this->managedObjectContext->persistentStoreCoordinator?->managedObjectModel?->entitiesByName?->valueForKey($this->request->url->lastPathComponent) !== null;
    }

    public function response(): HTTPURLResponse
    {
        $context = $this->managedObjectContext;
        $request = $this->request;
        $statusCode = HTTPStatusCode::ok;
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
                    return new BatchResponse($request->url, $fetchRequestResult, $fetchRequest);
                }
                if ($request->httpMethod === HTTPRequestMethod::get) {
                    $this->content = json_encode($fetchRequestResult, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
                    $this->contentType = "application/json; charset=utf-8";
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
                if ($request->httpMethod !== HTTPRequestMethod::post && !isset($body["objectID"])) {
                    $components = new URLComponents($this->request->url->absoluteString);
                    if ($item = $components->queryItems?->first(fn(URLQueryItem $item): bool => $item->name === "objectID")) {
                        $body[$item->name] = (int)$item->value;
                    }
                }
                $object = null;
                $objectID = $body["objectID"] ?? null;
                if ($objectID === null) {
                    if ($request->httpMethod !== HTTPRequestMethod::post) {
                        throw new BadRequestException("objectID cannot be null");
                    }
                } else {
                    $store = $context->persistentStoreCoordinator?->persistentStores?->first(fn(PersistentStore $store): bool => $store->type === XMLStoreType);
                    if ($store instanceof AtomicStore) {
                        $objectID = $store->objectID($this->entity, $objectID);
                    }
                    /** @var FetchRequest<ManagedObject> $fetchRequest */
                    $fetchRequest = new FetchRequest();
                    $fetchRequest->entity = $this->entity;
                    $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("objectID"), Expression::expressionForConstantValue($objectID));
                    if ($serialization = $this->serialization) {
                        $fetchRequest->serialization = $serialization;
                    }
                    $object = $context->fetch($fetchRequest)->first();
                }
                if (!$object instanceof ManagedObject) {
                    if ($request->httpMethod !== HTTPRequestMethod::post) {
                        throw new NotFoundException();
                    }
                } elseif ($request->httpMethod === HTTPRequestMethod::post) {
                    throw new ConflictException();
                }
                if ($request->httpMethod === HTTPRequestMethod::delete) {
                    /** @psalm-suppress PossiblyNullArgument */
                    $context->delete($object);
                    $context->save();
                    $statusCode = HTTPStatusCode::noContent;
                } else {
                    /** @var Dictionary<mixed> $keyedValues */
                    $keyedValues = Dictionary::dictionaryWithArray($body);
                    $object ??= EntityDescription::insertNewObject($this->entity->name, $context);
                    $object->setValuesForKeys($keyedValues);
                    $context->save();
                    $this->content = json_encode($object->serialized($this->serialization), JSON_PRESERVE_ZERO_FRACTION);
                    $this->contentType = "application/json; charset=utf-8";
                }
                break;
            case HTTPRequestMethod::options:
                break;
            default:
                throw new MethodNotAllowedException();
        }
        return new HTTPURLResponse($request->url, $statusCode);
    }
}
