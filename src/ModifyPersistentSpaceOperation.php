<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Closure;
use Exception;
use Override;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\Foundation\Dictionary;

/**
 * Modifies an object the subject may read and write through a domain change rather than a set of values, and returns it as stored, filtered to the fields the subject may read.
 *
 * The rules apply to the object named, not to the objects the change reaches through it.
 *
 * @internal
 * @extends PersistentSpaceOperation<Dictionary<mixed>>
 */
final class ModifyPersistentSpaceOperation extends PersistentSpaceOperation
{
    /**
     * @param Closure(ManagedObject): void $modification
     * @param Dictionary<mixed>|null $serialization
     */
    public function __construct(ManagedObjectContext $context, FieldSecurityPolicy $fieldSecurityPolicy, private readonly EntityDescription $entity, private readonly ManagedObjectID|int|string $objectID, private readonly Closure $modification, private readonly ?Dictionary $serialization = null)
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
        ($this->modification)($object);
        if ($this->context->hasChanges) {
            $this->context->save();
        }
        return $this->storedRepresentation($object, $this->serialization);
    }
}
