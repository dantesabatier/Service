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

/**
 * Built-in responder for the Core Data persistent history API at {@code /history}.
 *
 * Handles GET (fetch history transactions) and DELETE (purge history). Overrides
 * {@code $response} directly because the two verbs produce incompatible response
 * shapes: GET returns a JSON body while DELETE returns 204 No Content with no body —
 * too divergent to route through the standard {@code $data} + transformer chain.
 *
 * Requires persistent history tracking to be enabled via
 * {@code PersistentHistoryTrackingKey} in UserDefaults; Application sets this option
 * automatically on the store description when configured.
 *
 * @internal
 */
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
