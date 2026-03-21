<?php

namespace Sabatier\Service;

use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/** @internal */
final class PersistentSpaceResponseStrategyResolver
{
    /** @var Dictionary<class-string<PersistentSpaceResponseStrategy>> */
    private Dictionary $byHTTPMethodResponseStrategyClassesTable {
        get {
            if (!isset($this->byHTTPMethodResponseStrategyClassesTable)) {
                $this->byHTTPMethodResponseStrategyClassesTable = new Dictionary();
                $this->byHTTPMethodResponseStrategyClassesTable[$this->request->httpMethod] = match ($this->request->httpMethod) {
                    HTTPRequestMethod::get => ReadPersistentSpaceResponseStrategy::class,
                    HTTPRequestMethod::post => CreatePersistentSpaceResponseStrategy::class,
                    HTTPRequestMethod::patch => UpdatePersistentSpaceResponseStrategy::class,
                    HTTPRequestMethod::delete => DeletePersistentSpaceResponseStrategy::class,
                    default => throw new MethodNotAllowedException(),
                };
            }
            return $this->byHTTPMethodResponseStrategyClassesTable;
        }
    }
    public PersistentSpaceResponseStrategy $strategy {
        get {
            /** @var class-string<PersistentSpaceResponseStrategy> $responseStrategyClass */
            $responseStrategyClass = $this->byHTTPMethodResponseStrategyClassesTable[$this->request->httpMethod];
            return new $responseStrategyClass($this->request, $this->entity, $this->managedObjectContext, $this->securityPolicy);
        }
    }

    public function __construct(public readonly Request $request, public readonly EntityDescription $entity, public readonly ManagedObjectContext $managedObjectContext, public readonly ManagedObjectSecurityPolicy $securityPolicy)
    {
    }
}
