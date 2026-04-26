<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\Set;
use function Sabatier\Foundation\string_split_trimmed;

/**
 * Defines an immutable Cross-Origin Resource Sharing (CORS) policy.
 *
 * A CORSPolicy describes which origins, HTTP methods, and headers are allowed for cross-origin requests, and whether credentials are permitted.
 *
 * This class is a value object and does not apply CORS headers by itself.
 * Evaluation and application are delegated to other components in the pipeline.
 */
final readonly class CORSPolicy
{
    /** @var bool Indicates whether this policy contains no effective rules. */
    public bool $isEmpty;

    /**
     * Creates a new CORS policy instance.
     *
     * @param Set<string> $allowedOrigins Set of allowed origins. Use "*" to allow any origin.
     * @param Set<string> $allowedMethods Set of allowed HTTP methods.
     * @param Set<string> $allowedHeaders Set of allowed HTTP headers.
     * @param bool $allowCredentials Whether credentials are allowed.
     */
    public function __construct(public Set $allowedOrigins = new Set(), public Set $allowedMethods = new Set(), public Set $allowedHeaders = new Set(), public bool $allowCredentials = false, public Set $exposedHeaders = new Set())
    {
        $this->isEmpty = $this->allowedOrigins->isEmpty && $this->allowedMethods->isEmpty && $this->allowedHeaders->isEmpty && !$this->allowCredentials;
    }

    /**
     * Creates a CORS policy from the current process environment.
     * @return CORSPolicy
     */
    public static function policy(): CORSPolicy
    {
        $environment = ProcessInfo::processInfo()->environment;
        return new CORSPolicy(new Set(string_split_trimmed((string)$environment[CORSAllowedOriginsKey])), new Set(string_split_trimmed((string)$environment[CORSAllowedMethodsKey])), new Set(string_split_trimmed((string)$environment[CORSAllowedHeadersKey])), filter_var($environment[CORSAllowCredentialsKey], FILTER_VALIDATE_BOOL), new Set(string_split_trimmed((string)($environment[CORSExposedHeadersKey] ?? ""))));
    }

    /**
     * Determines whether this policy allows the given origin.
     *
     * An origin is allowed if:
     * - It exactly matches one of the allowed origins, or
     * - The wildcard "*" is present in the allowed origins set.
     *
     * @param string $origin The origin to evaluate.
     * @return bool True if the origin is allowed, false otherwise.
     */
    public function allowsOrigin(string $origin): bool
    {
        return $this->allowedOrigins->contains(fn($allowedOrigin) => $allowedOrigin === "*" || $origin === $allowedOrigin);
    }
}
