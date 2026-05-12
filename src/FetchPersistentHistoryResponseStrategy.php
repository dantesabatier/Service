<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\PersistentHistoryResult;

/** @internal */
final class FetchPersistentHistoryResponseStrategy extends PersistentHistoryResponseStrategy
{
    public PersistentHistoryResult $persistentHistoryResult {
        /**
         * @throws Exception
         */
        get {
            $changeRequest = $this->changeRequest;
            if ($fetchRequest = $this->fetchRequest) {
                $changeRequest->fetchRequest = $fetchRequest;
            }
            /** @var PersistentHistoryResult */
            return $this->managedObjectContext->execute($changeRequest);
        }
    }
    #[Override]
    public Response $response {
        /**
         * @throws Exception
         */
        get => new Response($this->request->url, body: $this->persistentHistoryResult->result);
    }
}
