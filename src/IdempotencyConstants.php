<?php

namespace Sabatier\Service;

const IdempotencyEnabledKey = "IDEMPOTENCY_ENABLED";
const IdempotencyTTLKey = "IDEMPOTENCY_TTL";
const IdempotencyEnabledDefault = true;
const IdempotencyTTLDefault = 86400;
const IdempotencyHeaderName = "Idempotency-Key";
