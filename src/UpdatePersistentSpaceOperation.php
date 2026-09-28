<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\Foundation\Dictionary;

/**
 * Updates an object the subject may read and write with the values the subject may write, and returns it as stored, filtered to the fields the subject may read.
 *
 * @internal
 * @extends PersistentSpaceOperation<Dictionary<mixed>>
 */
final class UpdatePersistentSpaceOperation extends PersistentSpaceOperation
{
    /**
     * @param Dictionary<mixed> $values
     * @param Dictionary<mixed>|null $serialization
     */
    public function __construct(ManagedObjectContext $context, FieldSecurityPolicy $fieldSecurityPolicy, private readonly EntityDescription $entity, private readonly ManagedObjectID|int|string $objectID, private readonly Dictionary $values, private readonly ?Dictionary $serialization = null)
    {
        parent::__construct($context, $fieldSecurityPolicy);
    }

    /**
     * @return Dictionary<mixed>
     * @throws Exception
     */
    #[Override]
    protected function performCore(): Dictionary
    {
        $object = $this->objectForWriting($this->entity, $this->objectID, $this->serialization);
        $this->fieldSecurityPolicy->enforceOwnership($object, AuthorizationType::update);
        $this->fieldSecurityPolicy->enforceResourceAccess($object);
        $this->fieldSecurityPolicy->applySecureUpdate($object, $this->values);
        if ($this->context->hasChanges) {
            $this->context->save();
        }
        return $this->storedRepresentation($object, $this->serialization);
    }
}
