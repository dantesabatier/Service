<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\Foundation\Dictionary;

/** @internal */
final class ReadPersistentSpaceResponseStrategy extends PersistentSpaceResponseStrategy
{
    public FetchRequest $fetchRequest {
        /**
         * @throws Exception
         */
        get {
            $fetchRequest = new RequestToFetchRequestAdapter($this->request, $this->managedObjectContext)->fetchRequest;
            $fetchRequest->entity = $this->entity;
            return $fetchRequest;
        }
    }
    #[Override]
    public Response $response {
        /**
         * @throws Exception
         */
        get {
            $fetchRequest = $this->fetchRequest;
            if ($fetchRequest->resultType === FetchRequestResultType::countResultType) {
                return new Response($this->request->url, body: new Dictionary([ServiceResponseCountKey => new CountPersistentSpaceOperation($this->managedObjectContext, $this->fieldSecurityPolicy, $fetchRequest)->perform()]));
            }
            $rows = new ReadPersistentSpaceOperation($this->managedObjectContext, $this->fieldSecurityPolicy, $fetchRequest)->perform();
            if ($fetchRequest->fetchBatchSize && match ($fetchRequest->resultType) {
                    FetchRequestResultType::managedObjectResultType, FetchRequestResultType::managedObjectIDResultType => true,
                    default => false
                }) {
                return new StreamResponse($this->request->url, $rows, chunkSize: $fetchRequest->fetchBatchSize, transform: null);
            }
            return new Response($this->request->url, body: $rows);
        }
    }
}
