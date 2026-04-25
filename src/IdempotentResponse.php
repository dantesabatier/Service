<?php

namespace Sabatier\Service;

use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPStatusCode;

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
     * @param Dictionary<string> $headers The response headers after the user pipeline.
     * @param mixed $body The response body after the user pipeline (typically a JSON string).
     */
    public function __construct(
        #[ExpectedValues(valuesFromClass: HTTPStatusCode::class)]
        public int $statusCode,
        public Dictionary $headers,
        public mixed $body
    ) {
    }
}
