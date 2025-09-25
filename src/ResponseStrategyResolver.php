<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;

/**
 * Provides an abstract base class for resolving a response strategy based on the HTTP method of a given request.
 */
abstract class ResponseStrategyResolver
{
    /** @var Dictionary<class-string<ResponseStrategy>> */
    protected(set) Dictionary $byHTTPMethodResponseStrategyClassesTable {
        get => $this->byHTTPMethodResponseStrategyClassesTable ??= new Dictionary();
    }
    public ResponseStrategy $strategy {
        get {
            if (!isset($this->strategy)) {
                $responder = $this->responder;
                $responseStrategyClass = $this->byHTTPMethodResponseStrategyClassesTable[$responder->request->httpMethod] ?? throw new MethodNotAllowedException();
                $this->strategy = new $responseStrategyClass($responder);
            }
            return $this->strategy;
        }
    }

    public function __construct(public readonly Responder $responder)
    {
    }
}
