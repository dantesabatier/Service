<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/** @internal */
final class PersistentSpaceResponseStrategyResolver
{
    public PersistentSpaceResponseStrategy $strategy {
        get {
            $class = match ($this->request->httpMethod) {
                HTTPRequestMethod::post => CreatePersistentSpaceResponseStrategy::class,
                HTTPRequestMethod::get => ReadPersistentSpaceResponseStrategy::class,
                HTTPRequestMethod::patch => UpdatePersistentSpaceResponseStrategy::class,
                HTTPRequestMethod::delete => DeletePersistentSpaceResponseStrategy::class,
                default => throw new MethodNotAllowedException(),
            };
            return new $class($this->request, $this->entity, $this->managedObjectContext, $this->fieldSecurityPolicy);
        }
    }

    public function __construct(public readonly Request $request, public readonly EntityDescription $entity, public readonly ManagedObjectContext $managedObjectContext, public readonly FieldSecurityPolicy $fieldSecurityPolicy)
    {
    }
}
