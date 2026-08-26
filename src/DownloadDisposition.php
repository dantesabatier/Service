<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\URL;

/**
 * Describes whether one file may be downloaded, and which file that is.
 *
 * The request names a subdirectory and a filename, not a path, so the resolved URL is always inside the document root by construction. The default implementation then asks {@see StaticResourcePolicy} about the resolved URL, so a download answers to the same rules a static request does — dotfiles refused, the public/protected distinction respected — rather than deciding on its own.
 */
final readonly class DownloadDisposition
{
    /**
     * @param bool $isAllowed Whether the download may proceed. A denied disposition carries no resource.
     * @param URL|null $resourceURL The resolved file to read, or `null` when the download is denied.
     * @param bool $isProtectedContentAvailable Whether the file may be served even though it lives outside the standard public directories. Carried through so the responder can require authentication exactly where a static request would.
     * @param string|null $failureReason Why the download was denied, in terms the client can act on. Ignored when allowed.
     */
    public function __construct(public bool $isAllowed, public ?URL $resourceURL = null, public bool $isProtectedContentAvailable = false, public ?string $failureReason = null)
    {
    }
}
