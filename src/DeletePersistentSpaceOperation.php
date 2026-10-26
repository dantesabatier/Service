<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectID;

/**
 * Deletes an object the subject may read and delete.
 *
 * @internal
 * @extends WritePersistentSpaceOperation<null>
 */
final class DeletePersistentSpaceOperation extends WritePersistentSpaceOperation
{
    #[Override]
    protected AuthorizationType $authorizationType {
        get => AuthorizationType::delete;
    }
    #[Override]
    protected ManagedObject $subject {
        /** @throws Exception */
        get => $this->subject ??= $this->objectForWriting($this->entity, $this->objectID);
    }
    /** @var null A deleted row has no stored representation to read back. */
    #[Override]
    protected mixed $answer {
        get => null;
    }

    /**
     * @param ManagedObjectContext $context
     * @param FieldSecurityPolicy $fieldSecurityPolicy
     * @param EntityDescription $entity
     * @param ManagedObjectID|int|string $objectID
     */
    public function __construct(ManagedObjectContext $context, FieldSecurityPolicy $fieldSecurityPolicy, EntityDescription $entity, private readonly ManagedObjectID|int|string $objectID)
    {
        parent::__construct($context, $fieldSecurityPolicy, $entity);
    }

    /**
     * @throws Exception
     */
    #[Override]
    protected function mutate(ManagedObject $object): void
    {
        $this->context->delete($object);
    }
}
