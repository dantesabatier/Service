<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;

abstract class ResponseStrategyResolver
{
    /** @var Dictionary<class-string<ResponseStrategy>> */
    protected(set) Dictionary $byHTTPMethodResponseStrategyClassesTable {
        get => $this->byHTTPMethodResponseStrategyClassesTable ??= new Dictionary();
    }
    public ResponseStrategy $strategy {
        get {
            $responder = $this->responder;
            $responseStrategyClass = $this->byHTTPMethodResponseStrategyClassesTable[$responder->request->httpMethod] ?? throw new MethodNotAllowedException();
            return new $responseStrategyClass($responder);
        }
    }

    public function __construct(public readonly Responder $responder)
    {
    }
}
