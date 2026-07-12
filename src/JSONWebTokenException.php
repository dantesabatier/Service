<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use const Sabatier\Foundation\LocalizedDescriptionKey;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;

/**
 * Represents an exception specific to JSON Web Token (JWT) handling.
 *
 * Typically, thrown when an error related to JWT creation, decoding, or validation occurs.
 */
final class JSONWebTokenException extends UnauthorizedException
{
    private ?int $errorCode;
    #[Override]
    protected(set) Error $error {
        get => $this->error ??= new Error(ServiceErrorDomain, $this->errorCode ?? $this->code, new Dictionary([LocalizedDescriptionKey => HTTPURLResponse::localizedString($this->code), LocalizedFailureReasonErrorKey => $this->message ?: null]));
    }

    public function __construct(string $message = "", ?int $code = null)
    {
        parent::__construct($message);
        $this->errorCode = $code;
    }
}
