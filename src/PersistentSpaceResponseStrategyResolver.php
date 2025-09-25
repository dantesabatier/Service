<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPRequestMethod;

/** @internal */
class PersistentSpaceResponseStrategyResolver extends ResponseStrategyResolver
{
    public function __construct(Responder $responder)
    {
        parent::__construct($responder);
        $this->byHTTPMethodResponseStrategyClassesTable[$responder->request->httpMethod] = match ($responder->request->httpMethod) {
            HTTPRequestMethod::get => PersistentSpaceGetResponseStrategy::class,
            HTTPRequestMethod::post => PersistentSpacePostResponseStrategy::class,
            HTTPRequestMethod::patch => PersistentSpacePatchResponseStrategy::class,
            HTTPRequestMethod::delete => PersistentSpaceDeleteResponseStrategy::class,
            HTTPRequestMethod::options => DefaultResponseStrategy::class,
            default => throw new MethodNotAllowedException(),
        };
    }
}
