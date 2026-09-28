<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

/**
 * An operation on the persistent space that both HTTP and MCP go through, so the security rules it
 * applies live in one place and run in one order: {@see self::perform()} runs {@see self::willPerform()},
 * {@see self::performCore()} and {@see self::didPerform()}, and nothing reaches the data any other way.
 *
 * @internal
 * @template TResult
 */
abstract class PersistentSpaceOperation
{
    public function __construct(protected readonly ManagedObjectContext $context, protected readonly FieldSecurityPolicy $fieldSecurityPolicy)
    {
    }

    /**
     * Performs the operation.
     *
     * @return TResult
     * @throws Exception
     */
    final public function perform(): mixed
    {
        $this->willPerform();
        $result = $this->performCore();
        $this->didPerform();
        return $result;
    }

    /**
     * @return TResult
     * @throws Exception
     */
    abstract protected function performCore(): mixed;

    /**
     * @throws Exception
     */
    protected function willPerform(): void
    {
    }

    /**
     * @throws Exception
     */
    protected function didPerform(): void
    {
    }

    /**
     * @param Dictionary<mixed>|null $serialization
     * @return FetchRequest<ManagedObject>
     */
    final protected function fetchRequestForObjectID(EntityDescription $entity, ManagedObjectID|int|string $objectID, ?Dictionary $serialization = null): FetchRequest
    {
        if (is_string($objectID) && is_numeric($objectID)) {
            $objectID = (int)$objectID;
        }
        /** @var FetchRequest<ManagedObject> $fetchRequest */
        $fetchRequest = new FetchRequest();
        $fetchRequest->entity = $entity;
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath(ManagedObjectObjectIDKey), Expression::expressionForConstantValue($objectID));
        if ($serialization) {
            $fetchRequest->serialization = $serialization;
        }
        return $fetchRequest;
    }

    /**
     * @param Dictionary<mixed>|null $serialization
     * @throws NotFoundException
     * @throws Exception
     */
    final protected function objectForWriting(EntityDescription $entity, ManagedObjectID|int|string $objectID, ?Dictionary $serialization = null): ManagedObject
    {
        $fetchRequest = $this->fetchRequestForObjectID($entity, $objectID, $serialization);
        $this->fieldSecurityPolicy->applyReadScope($fetchRequest);
        /** @var ManagedObject */
        return $this->context->fetch($fetchRequest)->first ?? throw new NotFoundException();
    }

    /**
     * @param Dictionary<mixed>|null $serialization
     * @return Dictionary<mixed>
     * @throws Exception
     */
    final protected function storedRepresentation(ManagedObject $object, ?Dictionary $serialization): Dictionary
    {
        /** @var ManagedObject $stored */
        $stored = $this->context->fetch($this->fetchRequestForObjectID($object->entity, $object->objectID, $serialization))->first ?? throw new InternalServerErrorException("failed to fetch the stored object");
        return $this->fieldSecurityPolicy->applySecureRead($stored, $stored->jsonSerialize());
    }
}
