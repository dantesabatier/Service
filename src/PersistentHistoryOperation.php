<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\PersistentHistoryChangeRequest;
use Sabatier\CoreData\PersistentHistoryResult;

/**
 * Executes a persistent history request, narrowed to the transactions a filter selects when one is given.
 *
 * @internal
 */
final readonly class PersistentHistoryOperation
{
    public function __construct(private ManagedObjectContext $context, private PersistentHistoryChangeRequest $changeRequest, private ?FetchRequest $transactionFilter = null)
    {
    }

    /**
     * Performs the operation.
     *
     * @throws Exception
     */
    public function perform(): PersistentHistoryResult
    {
        if ($this->transactionFilter) {
            $this->changeRequest->fetchRequest = $this->transactionFilter;
        }
        /** @var PersistentHistoryResult */
        return $this->context->execute($this->changeRequest);
    }
}
