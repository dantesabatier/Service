<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Number;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

/**
 * Creates an object from the values the subject may write, and returns it as stored, filtered to the fields the subject may read.
 *
 * @internal
 * @extends PersistentSpaceOperation<Dictionary<mixed>>
 */
final class CreatePersistentSpaceOperation extends PersistentSpaceOperation
{
    /**
     * @param Dictionary<mixed> $values
     * @param Dictionary<mixed>|null $serialization
     */
    public function __construct(ManagedObjectContext $context, FieldSecurityPolicy $fieldSecurityPolicy, private readonly EntityDescription $entity, private readonly Dictionary $values, private readonly ?Dictionary $serialization = null)
    {
        parent::__construct($context, $fieldSecurityPolicy);
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

    /**
     * @return Dictionary<mixed>
     * @throws Exception
     */
    #[Override]
    protected function performCore(): Dictionary
    {
        $object = EntityDescription::insertNewObject($this->entity->name, $this->context);
        $this->fieldSecurityPolicy->applySecureUpdate($object, $this->values);
        $this->fieldSecurityPolicy->enforceResourceAccess($object);
        $this->context->save();
        return $this->storedRepresentation($object, $this->serialization);
    }
}
