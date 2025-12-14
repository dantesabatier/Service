<?php

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final class InterfaceImplementorResolver
{
    /** @var Dictionary<class-string<ManagedObject>> */
    private Dictionary $index {
        get {
            if (!isset($this->index)) {
                static $targets = [
                    Authorizable::class => true,
                    Authorization::class => true,
                ];
                $this->index = new Dictionary();
                foreach ($this->model as $entity) {
                    if (!($class = $entity->managedObjectClassName)) {
                        continue;
                    }
                    if (!class_exists($class)) {
                        continue;
                    }
                    if (!($implements = class_implements($class))) {
                        continue;
                    }
                    foreach ($implements as $implement) {
                        if (!isset($targets[$implement])) {
                            continue;
                        }
                        $this->index[$implement] ??= $class;
                    }
                }
            }
            return $this->index;
        }
    }

    public function __construct(private readonly ManagedObjectModel $model)
    {
    }

    /**
     * Resolves the entity description for the given interface.
     *
     * @param class-string<Authorizable>|class-string<Authorization> $interface The interface to resolve.
     * @return class-string<ManagedObject> The managed object class implementing the interface.
     */
    public function resolve(string $interface): string
    {
        /** @var class-string<ManagedObject> */
        return $this->index[$interface] ?? fatal_error("No implementor found for interface \"$interface\". You must define an entity whose managed object class implements this interface and register it in the ManagedObjectModel.");
    }
}
