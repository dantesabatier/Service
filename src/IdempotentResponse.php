<?php

namespace Sabatier\Service;

/**
 * An immutable snapshot of a response stored by the idempotency layer.
 *
 * Produced by `Responder` after the user pipeline completes and persisted via `IdempotencyStore`.
 * On replay, the snapshot is reconstructed into a `Response` and passed directly to the
 * infrastructure pipeline, bypassing the user pipeline and action execution entirely.
 *
 * @see IdempotencyStore
 * @see IdempotencyPolicy
 */
final readonly class IdempotentResponse
{
    /**
     * @param int $statusCode The HTTP status code of the stored response.
     * @param array<string, string> $headers The response headers after the user pipeline.
     * @param mixed $body The response body after the user pipeline (typically a JSON string).
     */
    public function __construct(public int $statusCode, public array $headers, public mixed $body)
    {
    }
}
