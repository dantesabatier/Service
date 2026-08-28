<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Service\InvalidRequestException;
use Throwable;

/** Signals that an external tool provider failed before returning a result the agent can use, while preserving the framework's structured HTTP error path. */
final class LLMToolProviderException extends InvalidRequestException
{
    /** @var bool Whether a later run may succeed without changing its request. */
    private(set) bool $isTransient;
    /** @var Throwable|null The provider-specific failure normalized at the executor boundary. */
    private(set) ?Throwable $providerError;

    /**
     * @param string $message What prevented the external tool provider from answering.
     * @param bool $isTransient Whether a later run may succeed without changing its request.
     * @param Throwable|null $providerError The provider-specific failure being normalized.
     */
    public function __construct(string $message = "", bool $isTransient = false, ?Throwable $providerError = null)
    {
        parent::__construct($message, HTTPStatusCode::internalServerError);
        $this->isTransient = $isTransient;
        $this->providerError = $providerError;
    }
}
