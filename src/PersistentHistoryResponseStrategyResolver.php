<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/** @internal */
final class PersistentHistoryResponseStrategyResolver
{
    public PersistentHistoryResponseStrategy $strategy {
        get {
            $class = match ($this->request->httpMethod) {
                HTTPRequestMethod::get => FetchPersistentHistoryResponseStrategy::class,
                HTTPRequestMethod::delete => DeletePersistentHistoryResponseStrategy::class,
                default => throw new MethodNotAllowedException(),
            };
            return new $class($this->request, $this->managedObjectContext);
        }
    }

    public function __construct(public readonly Request $request, public readonly ManagedObjectContext $managedObjectContext)
    {
    }
}
