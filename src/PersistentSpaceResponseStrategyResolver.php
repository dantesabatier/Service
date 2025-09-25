<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPRequestMethod;

/** @internal */
class PersistentSpaceResponseStrategyResolver extends ResponseStrategyResolver
{
    public function __construct(Responder $responder)
    {
        parent::__construct($responder);
        $this->byHTTPMethodResponseStrategyClassesTable[HTTPRequestMethod::get] = PersistentSpaceGetResponseStrategy::class;
        $this->byHTTPMethodResponseStrategyClassesTable[HTTPRequestMethod::post] = PersistentSpacePostResponseStrategy::class;
        $this->byHTTPMethodResponseStrategyClassesTable[HTTPRequestMethod::patch] = PersistentSpacePatchResponseStrategy::class;
        $this->byHTTPMethodResponseStrategyClassesTable[HTTPRequestMethod::delete] = PersistentSpaceDeleteResponseStrategy::class;
        $this->byHTTPMethodResponseStrategyClassesTable[HTTPRequestMethod::options] = DefaultResponseStrategy::class;
    }
}
