<?php

declare(strict_types=1);

namespace Sabatier\Service;

/** @var string Environment variable key to enable or disable rate limiting (`true` or `false`). */
const RateLimitEnabledKey = "RATE_LIMIT_ENABLED";
/** @var string Environment variable key for the maximum number of requests per window for authenticated users. */
const RateLimitMaxRequestsUserKey = "RATE_LIMIT_MAX_REQUESTS_USER";
/** @var string Environment variable key for the maximum number of requests per window for unauthenticated (IP-based) clients. */
const RateLimitMaxRequestsIPKey = "RATE_LIMIT_MAX_REQUESTS_IP";
/** @var string Environment variable key for the rate limit window duration in seconds. */
const RateLimitWindowSecondsKey = "RATE_LIMIT_WINDOW_SECONDS";
/** @var bool Default rate limiting state. */
const RateLimitEnabledDefault = true;
/** @var int Default maximum requests per window for authenticated users. */
const RateLimitMaxRequestsUserDefault = 120;
/** @var int Default maximum requests per window for unauthenticated (IP-based) clients. */
const RateLimitMaxRequestsIPDefault = 30;
/** @var int Default rate limit window duration in seconds. */
const RateLimitWindowSecondsDefault = 60;
