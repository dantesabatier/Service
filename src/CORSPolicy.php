<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

/**
 * Defines the Cross-Origin Resource Sharing (CORS) policy applied to responses.
 *
 * CORSPolicy is a declarative object that describes which origins, HTTP methods,
 * and request headers are allowed to access the service. The policy is consumed
 * by internal response decorators and responders; it does not perform any I/O
 * by itself.
 *
 * Origins are evaluated explicitly:
 * - An origin is allowed only if it is present in `allowedOrigins` with the value `true`.
 * - The special key "*" may be used to allow any origin.
 * - An empty `allowedOrigins` dictionary disables CORS entirely.
 *
 * When credentials are allowed, the wildcard origin ("*") is never reflected
 * in the response, in accordance with the CORS specification.
 *
 * Allowed methods and headers are treated as strict whitelists and are
 * normalized by the framework before being emitted in response headers.
 */
final readonly class CORSPolicy
{
    /**
     * Creates a new CORS policy.
     *
     * @param Dictionary $allowedOrigins
     *        A dictionary of allowed origins. Keys are origin strings
     *        (for example, "https://example.com") or the special "*" key.
     *        Values must be boolean and set to true to explicitly allow
     *        the corresponding origin.
     *
     * @param Set $allowedMethods
     *        A set of allowed HTTP methods. Values are compared case-insensitively
     *        and normalized before being emitted.
     *
     * @param Set $allowedHeaders
     *        A set of allowed request headers. Header names are normalized and
     *        evaluated strictly during preflight requests.
     *
     * @param bool $allowCredentials
     *        Whether user credentials (cookies, authorization headers, TLS
     *        client certificates) are allowed to be included in cross-origin
     *        requests.
     */
    public function __construct(public Dictionary $allowedOrigins, public Set $allowedMethods, public Set $allowedHeaders, public bool $allowCredentials = false)
    {
    }

    /**
     * Determines whether this policy allows the given origin.
     *
     * An origin is considered allowed if:
     * - the "*" origin is explicitly enabled, or
     * - the exact origin string is present in `allowedOrigins` with value `true`.
     *
     * @param string $origin
     *        The Origin header value from the incoming request.
     *
     * @return bool
     *         True if the origin is allowed; false otherwise.
     */
    public function allowsOrigin(string $origin): bool
    {
        return $this->allowedOrigins["*"] === true || $this->allowedOrigins[$origin] === true;
    }
}
