<?php

declare(strict_types=1);

namespace Sabatier\Service;

/** @var string Environment variable key to enable or disable the idempotency layer (`true` or `false`). */
const IdempotencyEnabledKey = "IDEMPOTENCY_ENABLED";
/** @var string Environment variable key for the time-to-live of stored idempotent responses in seconds. */
const IdempotencyTTLKey = "IDEMPOTENCY_TTL";
/** @var bool Default idempotency state. */
const IdempotencyEnabledDefault = true;
/** @var int Default TTL for stored idempotent responses (86400 seconds = 24 hours). */
const IdempotencyTTLDefault = 86400;
/** @var string Name of the HTTP request header carrying the client-supplied idempotency key. */
const IdempotencyHeaderName = "Idempotency-Key";
/** @var int Maximum allowed length in characters for a client-supplied idempotency key. */
const IdempotencyKeyMaxLength = 255;
/** @var int TTL in seconds for in-flight sentinels written before an action executes. Limits the window during which a duplicate concurrent request receives 409. */
const IdempotencyInFlightTTL = 60;
