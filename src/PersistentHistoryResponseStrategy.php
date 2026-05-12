<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\PersistentHistoryChangeRequest;

/**
 * @psalm-consistent-constructor
 * @phpstan-consistent-constructor
 * @internal
 */
abstract class PersistentHistoryResponseStrategy extends ResponseStrategy
{
    protected PersistentHistoryChangeRequest $changeRequest {
        get => new PersistentHistoryChangeRequestAdapter($this->request)->changeRequest;
    }
    protected ?FetchRequest $fetchRequest {
        get {
            if (!$this->request->parameters[ServiceFetchRequestKey]) {
                return null;
            }
            return new RequestToFetchRequestAdapter($this->request, $this->managedObjectContext)->fetchRequest;
        }
    }

    public function __construct(Request $request, protected readonly ManagedObjectContext $managedObjectContext)
    {
        parent::__construct($request);
    }
}
