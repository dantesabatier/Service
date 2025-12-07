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
                $this->index = new Dictionary();
                foreach ($this->model as $entity) {
                    $class = $entity->managedObjectClassName ?? null;
                    if ($class === null) {
                        continue;
                    }
                    $implements = class_implements($class);
                    if (!$implements) {
                        continue;
                    }
                    foreach ($implements as $implement) {
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
     * @param class-string $interface The name of the interface to resolve.
     * @return class-string<ManagedObject> The class name of the implementor if found, or null otherwise.
     */
    public function resolve(string $interface): string
    {
        return $this->index[$interface] ?? fatal_error("No $interface implementor found.");
    }
}
