<?php

namespace Sabatier\Service;

/** @var string Environment variable key for the comma-separated list of allowed origins. */
const CORSAllowedOriginsKey = "CORS_ALLOWED_ORIGINS";
/** @var string Environment variable key for the comma-separated list of allowed HTTP methods. */
const CORSAllowedMethodsKey = "CORS_ALLOWED_METHODS";
/** @var string Environment variable key for the comma-separated list of allowed request headers. */
const CORSAllowedHeadersKey = "CORS_ALLOWED_HEADERS";
/** @var string Environment variable key for the boolean flag indicating whether credentials (cookies, authorization headers) are allowed in cross-origin requests. */
const CORSAllowCredentialsKey = "CORS_ALLOW_CREDENTIALS";
/** @var string Environment variable key for the comma-separated list of response headers exposed to the browser in cross-origin requests. */
const CORSExposedHeadersKey = "CORS_EXPOSED_HEADERS";
