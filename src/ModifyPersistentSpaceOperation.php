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
 * @extends WritePersistentSpaceOperation<Dictionary<mixed>>
 */
class ModifyPersistentSpaceOperation extends WritePersistentSpaceOperation
{
    #[Override]
    protected AuthorizationType $authorizationType {
        get => AuthorizationType::update;
    }
    #[Override]
    protected ManagedObject $subject {
        /** @throws Exception */
        get => $this->subject ??= $this->objectForWriting($this->entity, $this->objectID, $this->serialization);
    }

    /**
     * @param ManagedObjectContext $context
     * @param FieldSecurityPolicy $fieldSecurityPolicy
     * @param EntityDescription $entity
     * @param ManagedObjectID|int|string $objectID
     * @param Closure $modification
     * @param Dictionary<mixed>|null $serialization
     */
    public function __construct(ManagedObjectContext $context, FieldSecurityPolicy $fieldSecurityPolicy, EntityDescription $entity, private readonly ManagedObjectID|int|string $objectID, private readonly Closure $modification, ?Dictionary $serialization = null)
    {
        parent::__construct($context, $fieldSecurityPolicy, $entity, $serialization);
    }

    #[Override]
    protected function mutate(ManagedObject $object): void
    {
        ($this->modification)($object);
    }
}
