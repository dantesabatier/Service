<?php

namespace Sabatier\Service;

use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final class InterfaceImplementorResolver
{
    /** @var Dictionary<EntityDescription> */
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
                        $this->index[$implement] ??= $entity;
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
     * @param class-string<Authorizable>|class-string<Authorization> $interface The interface name to resolve.
     * @return EntityDescription The entity description of the implementor.
     */
    public function resolve(string $interface): EntityDescription
    {
        return $this->index[$interface] ?? fatal_error("No $interface implementor found.");
    }
}
