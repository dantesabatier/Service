<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Generator;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\ProcessInfo;

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
        $time = ProcessInfo::processInfo()->systemUptime;
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
            if (ProcessInfo::processInfo()->systemUptime - $time > 15) {
                $time = ProcessInfo::processInfo()->systemUptime;
                yield new ServerSentEvent("heartbeat", event: "ping");
            }
            usleep($this->interval);
        }
    }
}
