<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * Resolves a client IP address to an ISO 3166-1 alpha-2 country code.
 *
 * The framework never ships a geolocation database or service: geo lookup is an optional
 * capability the application supplies. Set `Application::$geoIPResolver` in the delegate to
 * enable `$REQUEST.country` in attribute-based access conditions. When no resolver is set,
 * `$REQUEST.country` is null, so any rule comparing against it fails closed (denies access).
 *
 * Implementations should be cheap and side-effect free — `resolve` runs once per request that
 * evaluates a country-based condition. A local database (e.g. MaxMind GeoLite2) is preferred over
 * a remote service on the request path.
 *
 * @see Application::$geoIPResolver
 * @see AccessConditionResolver
 */
interface GeoIPResolver
{
    /**
     * Returns the ISO 3166-1 alpha-2 country code for the given client IP, or null when it cannot be determined.
     *
     * @param string $ip The client IP address, as resolved by {@see ClientAddressResolver}.
     */
    public function resolve(string $ip): ?string;
}
