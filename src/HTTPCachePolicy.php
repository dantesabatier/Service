<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ProcessInfo;

final readonly class HTTPCachePolicy
{
    public function __construct(public int $maxAge = HTTPCacheMaxAgeDefault, public string $visibility = HTTPCacheVisibilityDefault, public ?int $staleWhileRevalidate = null, public ?string $vary = null, public bool $etagEnabled = HTTPCacheETagEnabledDefault)
    {
    }

    public static function policy(): HTTPCachePolicy
    {
        $environment = ProcessInfo::processInfo()->environment;
        return new HTTPCachePolicy((int)($environment[HTTPCacheMaxAgeKey] ?? HTTPCacheMaxAgeDefault), (string)($environment[HTTPCacheVisibilityKey] ?? HTTPCacheVisibilityDefault), $environment[HTTPCacheStaleWhileRevalidateKey], $environment[HTTPCacheVaryKey], filter_var($environment[HTTPCacheETagEnabledKey] ?? HTTPCacheETagEnabledDefault, FILTER_VALIDATE_BOOL));
    }
}
