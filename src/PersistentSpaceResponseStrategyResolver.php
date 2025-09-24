<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPRequestMethod;

/** @internal */
class PersistentSpaceResponseStrategyResolver
{
    public PersistentSpaceResponseStrategy $strategy {
        get {
            $persistentSpace = $this->persistentSpace;
            $responseStrategyClass = match ($persistentSpace->request->httpMethod) {
                HTTPRequestMethod::get => PersistentSpaceGetResponseStrategy::class,
                HTTPRequestMethod::post => PersistentSpacePostResponseStrategy::class,
                HTTPRequestMethod::patch => PersistentSpacePatchResponseStrategy::class,
                HTTPRequestMethod::delete => PersistentSpaceDeleteResponseStrategy::class,
                HTTPRequestMethod::options => DefaultResponseStrategy::class,
                default => throw new MethodNotAllowedException()
            };
            return new $responseStrategyClass($persistentSpace);
        }
    }

    public function __construct(private readonly PersistentSpace $persistentSpace)
    {
    }
}
