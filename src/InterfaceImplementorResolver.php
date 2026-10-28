<?php

declare(strict_types=1);

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
            if (isset($this->index)) {
                return $this->index;
            }
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
            return $this->index;
        }
    }

    /**
     * @param ManagedObjectModel $model
     */
    public function __construct(private readonly ManagedObjectModel $model)
    {
    }

    /**
     * @param class-string<Authorizable>|class-string<Authorization> $interface
     * @return class-string<ManagedObject>
     */
    public function resolve(string $interface): string
    {
        /** @noinspection PhpStrictTypeCheckingInspection */
        return $this->index[$interface] ?? fatal_error("No implementor found for interface \"$interface\". You must define an entity whose managed object class implements this interface and register it in the ManagedObjectModel.");
    }
}
