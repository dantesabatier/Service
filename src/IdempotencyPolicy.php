<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ProcessInfo;

/**
 * An immutable value object that describes the idempotency behavior for non-idempotent requests.
 *
 * When enabled, `Responder` checks for an `Idempotency-Key` header on POST and PATCH requests.
 * If a matching stored response exists, it is returned immediately without re-executing the action.
 * Otherwise, the action runs normally and the response is stored for later replays.
 *
 * ## Configuration
 * `Application::$idempotencyPolicy` is the central override point. The static `policy()` factory
 * reads from environment variables:
 *
 * - `IDEMPOTENCY_ENABLED` (default: `true`)
 * - `IDEMPOTENCY_TTL` (default: `86400` — 24 hours)
 *
 * Override in the application delegate for programmatic control:
 *
 * <code>
 * Application::shared()->idempotencyPolicy = new IdempotencyPolicy(ttl: 3600);
 * </code>
 *
 * @see IdempotencyStore
 * @see Application::$idempotencyPolicy
 */
final readonly class IdempotencyPolicy
{
    public function __construct(public bool $enabled = IdempotencyEnabledDefault, public int $ttl = IdempotencyTTLDefault, public string $headerName = IdempotencyHeaderName)
    {
    }

    public static function policy(): IdempotencyPolicy
    {
        $environment = ProcessInfo::processInfo()->environment;
        return new IdempotencyPolicy(filter_var($environment[IdempotencyEnabledKey] ?? IdempotencyEnabledDefault, FILTER_VALIDATE_BOOL), (int)($environment[IdempotencyTTLKey] ?? IdempotencyTTLDefault));
    }
}
