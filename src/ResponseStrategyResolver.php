<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;

abstract class ResponseStrategyResolver
{
    /** @var Dictionary<class-string<ResponseStrategy>> */
    protected(set) Dictionary $byHTTPMethodResponseStrategyCLassesTable {
        get => $this->byHTTPMethodResponseStrategyCLassesTable ??= new Dictionary();
    }
    public ResponseStrategy $strategy {
        get {
            $responder = $this->responder;
            $responseStrategyClass = $this->byHTTPMethodResponseStrategyCLassesTable[$responder->request->httpMethod] ?? throw new MethodNotAllowedException();
            return new $responseStrategyClass($responder);
        }
    }

    public function __construct(public readonly Responder $responder)
    {
    }
}
