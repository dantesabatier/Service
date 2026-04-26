<?php

declare(strict_types=1);

namespace Sabatier\Service;

/** @var string Environment variable key to enable or disable rate limiting (`true` or `false`). */
const RateLimitEnabledKey = "RATE_LIMIT_ENABLED";
/** @var string Environment variable key for the maximum number of requests allowed per window. */
const RateLimitMaxRequestsKey = "RATE_LIMIT_MAX_REQUESTS";
/** @var string Environment variable key for the rate limit window duration in seconds. */
const RateLimitWindowSecondsKey = "RATE_LIMIT_WINDOW_SECONDS";
/** @var bool Default rate limiting state. */
const RateLimitEnabledDefault = true;
/** @var int Default maximum number of requests allowed per window. */
const RateLimitMaxRequestsDefault = 60;
/** @var int Default rate limit window duration in seconds. */
const RateLimitWindowSecondsDefault = 60;
