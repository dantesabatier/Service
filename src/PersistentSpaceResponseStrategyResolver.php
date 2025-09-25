<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPRequestMethod;

/** @internal */
class PersistentSpaceResponseStrategyResolver extends ResponseStrategyResolver
{
    public function __construct(Responder $responder)
    {
        parent::__construct($responder);
        $this->byHTTPMethodResponseStrategyCLassesTable[HTTPRequestMethod::get] = PersistentSpaceGetResponseStrategy::class;
        $this->byHTTPMethodResponseStrategyCLassesTable[HTTPRequestMethod::post] = PersistentSpacePostResponseStrategy::class;
        $this->byHTTPMethodResponseStrategyCLassesTable[HTTPRequestMethod::patch] = PersistentSpacePatchResponseStrategy::class;
        $this->byHTTPMethodResponseStrategyCLassesTable[HTTPRequestMethod::delete] = PersistentSpaceDeleteResponseStrategy::class;
        $this->byHTTPMethodResponseStrategyCLassesTable[HTTPRequestMethod::options] = DefaultResponseStrategy::class;
    }
}
