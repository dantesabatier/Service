<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\Foundation\Dictionary;

/**
 * Updates an object the subject may read and write with the values the subject may write, and returns it as stored, filtered to the fields the subject may read.
 *
 * An update is a modification whose change the framework already knows how to make — applying the values the subject may write — so it is one, with that change named rather than supplied.
 *
 * @internal
 */
final class UpdatePersistentSpaceOperation extends ModifyPersistentSpaceOperation
{
    /**
     * @param ManagedObjectContext $context
     * @param FieldSecurityPolicy $fieldSecurityPolicy
     * @param EntityDescription $entity
     * @param ManagedObjectID|int|string $objectID
     * @param Dictionary<mixed> $values
     * @param Dictionary<mixed>|null $serialization
     */
    public function __construct(ManagedObjectContext $context, FieldSecurityPolicy $fieldSecurityPolicy, EntityDescription $entity, ManagedObjectID|int|string $objectID, Dictionary $values, ?Dictionary $serialization = null)
    {
        parent::__construct($context, $fieldSecurityPolicy, $entity, $objectID, static function (ManagedObject $object) use ($fieldSecurityPolicy, $values): void {
            $fieldSecurityPolicy->applySecureUpdate($object, $values);
        }, $serialization);
    }
}
