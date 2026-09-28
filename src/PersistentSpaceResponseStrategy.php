<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObjectContext;

/** @internal */
abstract class PersistentSpaceResponseStrategy extends ResponseStrategy
{
    protected readonly EntityDescription $entity;
    protected readonly ManagedObjectContext $managedObjectContext;
    protected readonly FieldSecurityPolicy $fieldSecurityPolicy;

    public function __construct(Request $request, EntityDescription $entity, ManagedObjectContext $managedObjectContext, FieldSecurityPolicy $fieldSecurityPolicy)
    {
        parent::__construct($request);
        $this->entity = $entity;
        $this->managedObjectContext = $managedObjectContext;
        $this->fieldSecurityPolicy = $fieldSecurityPolicy;
    }
}
