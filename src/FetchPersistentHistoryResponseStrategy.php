<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\CoreData\PersistentHistoryChange;
use Sabatier\CoreData\PersistentHistoryResult;
use Sabatier\CoreData\PersistentHistoryResultType;
use Sabatier\CoreData\PersistentHistoryTransaction;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;

/** @internal */
final class FetchPersistentHistoryResponseStrategy extends PersistentHistoryResponseStrategy
{
    public mixed $data {
        /**
         * @throws Exception
         */
        get {
            $context = $this->managedObjectContext;
            $changeRequest = $this->changeRequest;
            $changeRequest->fetchRequest = $this->fetchRequest;
            /** @var PersistentHistoryResult $persistentHistoryResult */
            $persistentHistoryResult = $context->execute($changeRequest);
            /** @var ArrayClass<ManagedObjectID|PersistentHistoryTransaction|PersistentHistoryChange|Number>|Number $result */
            $result = $persistentHistoryResult->result;
            return match ($changeRequest->resultType) {
                PersistentHistoryResultType::objectIDs, PersistentHistoryResultType::transactionsOnly, PersistentHistoryResultType::changesOnly, PersistentHistoryResultType::transactionsAndChanges => $result,
                PersistentHistoryResultType::statusOnly => new Dictionary([ServiceResponseStatusKey => $result]),
                PersistentHistoryResultType::count => new Dictionary([ServiceResponseCountKey => $result]),
            };
        }
    }
    #[Override]
    public Response $response {
        /**
         * @throws Exception
         */
        get => new Response($this->request->url, body: $this->data);
    }
}
