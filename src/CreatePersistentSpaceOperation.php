<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Number;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

/**
 * Creates an object from the values the subject may write, and returns it as stored, filtered to the fields the subject may read.
 *
 * The row is populated as it is obtained rather than in the change step, so the resource rule is enforced against the values being written: a `where` that reads them must see them.
 *
 * @internal
 * @extends WritePersistentSpaceOperation<Dictionary<mixed>>
 */
final class CreatePersistentSpaceOperation extends WritePersistentSpaceOperation
{
    #[Override]
    protected AuthorizationType $authorizationType {
        get => AuthorizationType::create;
    }
    #[Override]
    protected ManagedObject $subject {
        /** @throws Exception */
        get {
            if (isset($this->subject)) {
                return $this->subject;
            }
            $object = EntityDescription::insertNewObject($this->entity->name, $this->context);
            $this->fieldSecurityPolicy->applySecureUpdate($object, $this->values);
            return $this->subject = $object;
        }
    }

    /**
     * @param ManagedObjectContext $context
     * @param FieldSecurityPolicy $fieldSecurityPolicy
     * @param EntityDescription $entity
     * @param Dictionary<mixed> $values
     * @param Dictionary<mixed>|null $serialization
     */
    public function __construct(ManagedObjectContext $context, FieldSecurityPolicy $fieldSecurityPolicy, EntityDescription $entity, private readonly Dictionary $values, ?Dictionary $serialization = null)
    {
        parent::__construct($context, $fieldSecurityPolicy, $entity, $serialization);
    }

    /**
     * @throws ConflictException
     * @throws Exception
     */
    #[Override]
    protected function willPerform(): void
    {
        if (!($objectID = $this->values[ManagedObjectObjectIDKey])) {
            return;
        }
        /** @var FetchRequest<Number> $fetchRequest */
        $fetchRequest = $this->fetchRequestForObjectID($this->entity, $objectID);
        if ($this->context->count($fetchRequest)) {
            throw new ConflictException();
        }
    }

    #[Override]
    protected function mutate(ManagedObject $object): void
    {
    }
}
