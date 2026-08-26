<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\Error;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Service\InvalidRequestException;

/**
 * Raised when the provider itself failed to answer a request, after the client has stopped trying.
 *
 * The agentic loop must tell this apart from a fault in the code that talks to the provider. Both used to surface as `InternalInconsistencyException`, the root of the framework's exception hierarchy, so catching one caught the other: a deserialization bug in a client's `parse` came back as a provider failure, and the caller reasonably retried something no amount of retrying would fix. This type narrows the catch to the case it is meant for, and a program fault propagates as it should.
 *
 * It covers the two ways a provider stops answering — a status the client will not retry or has finished retrying, and a transport that never delivered the request at all — because the caller reacts to them identically: the run ends where it is, keeping the messages and tokens already paid for. Which of the two it was stays available through `$transportError`.
 *
 * `$isTransient` separates a failure worth retrying later from one that will answer the same way every time. A 429 or a 503 clears on its own; a 401 from a bad key or a 404 from an unknown model needs the configuration changed first, and telling a caller to retry it only delays the report.
 *
 * It reports HTTP 500 like `InternalServerErrorException`, which it cannot extend because that class is `final`: a request whose provider failed is a server-side failure to the client that made it, whatever went wrong upstream.
 */
final class LLMProviderException extends InvalidRequestException
{
    /** @var bool Whether the failure is the kind that may clear on its own, making a later attempt worthwhile. A definitive refusal answers the same way however often it is asked. */
    private(set) bool $isTransient;
    /** @var Error|null The transport error, when the request never reached the provider, and `null` when the provider answered with a status the client gave up on. */
    private(set) ?Error $transportError;

    /**
     * @param string $message What failed, in terms the caller can report, preferring the provider's own words when it sent any.
     * @param bool $isTransient Whether a later attempt is worth making.
     * @param Error|null $transportError The underlying transport error, when the failure was one.
     */
    public function __construct(string $message = "", bool $isTransient = false, ?Error $transportError = null)
    {
        parent::__construct($message, HTTPStatusCode::internalServerError);
        $this->isTransient = $isTransient;
        $this->transportError = $transportError;
    }
}
