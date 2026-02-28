<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use const Sabatier\Foundation\LocalizedDescriptionKey;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;

/**
 * Represents an exception thrown when an invalid request is encountered.
 */
abstract class InvalidRequestException extends InternalInconsistencyException
{
    #[Override]
    protected(set) Error $error {
        get => $this->error ??= new Error(ServiceErrorDomain, $this->code, new Dictionary([LocalizedDescriptionKey => HTTPURLResponse::localizedString($this->code), LocalizedFailureReasonErrorKey => $this->message ?: null]));
    }

    public function __construct(string $message = "", int $code = 0)
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $i = array_find_key($trace, fn($frame) => !is_a($frame["class"] ?? "", InternalInconsistencyException::class, true));
        $caller = is_int($i) ? ($trace[$i - 1] ?? $trace[$i]) : $trace[0];
        parent::__construct($message, $code, filename: $caller["file"] ?? null, line: $caller["line"] ?? null);
    }
}
