<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\Networking\HTTPStatusCode;

/** @internal */
final class DeletePersistentHistoryResponseStrategy extends PersistentHistoryResponseStrategy
{
    #[Override]
    public Response $response {
        /**
         * @throws Exception
         */
        get {
            $changeRequest = $this->changeRequest;
            if ($fetchRequest = $this->fetchRequest) {
                $changeRequest->fetchRequest = $fetchRequest;
            }
            $this->managedObjectContext->execute($changeRequest);
            return new Response($this->request->url, HTTPStatusCode::noContent);
        }
    }
}
