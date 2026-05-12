<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\PersistentHistoryResult;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Set;

#[Endpoint("/history", [JSONTransformer::class])]
final class PersistentHistoryResponder extends Responder
{
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::delete]);
    }

    #[Override]
    public Response $response {
        /**
         * @throws Exception
         */
        get {
            try {
                $this->allowedMethods->containsElement($this->request->httpMethod) ?: throw new MethodNotAllowedException();
                if ($this->isSessionEnabled) {
                    $this->session->start();
                }
                $changeRequest = new PersistentHistoryChangeRequestAdapter($this->request)->changeRequest;
                /** @var PersistentHistoryResult $result */
                $result = $this->managedObjectContext->execute($changeRequest);
                if ($changeRequest->isDelete) {
                    $userResponse = new Response($this->request->url, HTTPStatusCode::noContent);
                } else {
                    $userResponse = new ResponsePipeline(new Set([JSONTransformer::class, ResponseHeaderSanitizerTransformer::class]), $this->transformerContext)->process(new Response($this->request->url, body: $result->result));
                }
                return new ResponsePipeline($this->infrastructureTransformers, $this->transformerContext)->process($userResponse);
            } finally {
                if ($this->isSessionEnabled) {
                    $this->session->commit();
                }
            }
        }
    }
}
