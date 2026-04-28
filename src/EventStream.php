<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Exception;
use Generator;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use function Sabatier\Foundation\absolute_time_get_current;

/** @internal */
final readonly class EventStream
{
    public function __construct(private FetchRequest $fetchRequest, private ManagedObjectContext $context, private int $interval = 300_000)
    {
    }

    /**
     * @throws Exception
     */
    public function generator(): Generator
    {
        $previousHash = null;
        $time = absolute_time_get_current();
        while (true) {
            if (connection_aborted()) {
                break;
            }
            /** @var ArrayClass<ManagedObject> $managedObjects */
            $managedObjects = $this->context->fetch($this->fetchRequest);
            if (!$managedObjects->isEmpty) {
                $hash = md5($managedObjects->map(fn(ManagedObject $managedObject): string => "{$managedObject->objectID->referenceObject}:$managedObject->version")->join("|"));
                if ($hash !== $previousHash) {
                    $previousHash = $hash;
                    yield new ServerSentEvent($managedObjects);
                }
            }
            if (absolute_time_get_current() - $time > 15) {
                $time = absolute_time_get_current();
                yield new ServerSentEvent("heartbeat", event: "ping");
            }
            usleep($this->interval);
        }
    }
}
