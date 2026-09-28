<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectID;

/**
 * Deletes an object the subject may read and delete.
 *
 * @internal
 * @extends PersistentSpaceOperation<null>
 */
final class DeletePersistentSpaceOperation extends PersistentSpaceOperation
{
    public function __construct(ManagedObjectContext $context, FieldSecurityPolicy $fieldSecurityPolicy, private readonly EntityDescription $entity, private readonly ManagedObjectID|int|string $objectID)
    {
        parent::__construct($context, $fieldSecurityPolicy);
    }

    /**
     * @throws Exception
     */
    #[Override]
    protected function performCore(): null
    {
        $object = $this->objectForWriting($this->entity, $this->objectID);
        $this->fieldSecurityPolicy->enforceOwnership($object, AuthorizationType::delete);
        $this->fieldSecurityPolicy->enforceResourceAccess($object);
        $this->context->delete($object);
        $this->context->save();
        return null;
    }
}
