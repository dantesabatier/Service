<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Set;

/** @internal */
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
        get {
            try {
                $this->allowedMethods->containsElement($this->request->httpMethod) ?: throw new MethodNotAllowedException();
                if ($this->isSessionEnabled) {
                    $this->session->start();
                }
                $userResponse = new ResponsePipeline(new Set([JSONTransformer::class, ResponseHeaderSanitizerTransformer::class]), $this->transformerContext)->process(new PersistentHistoryResponseStrategyResolver($this->request, $this->managedObjectContext)->strategy->response);
                return new ResponsePipeline($this->infrastructureTransformers, $this->transformerContext)->process($userResponse);
            } finally {
                if ($this->isSessionEnabled) {
                    $this->session->commit();
                }
            }
        }
    }
}
