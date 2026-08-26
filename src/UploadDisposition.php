<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;

/**
 * Describes how one uploaded file should be handled.
 *
 * The destination is resolved by the policy rather than by the responder, so the root the file lands under is never negotiable: an upload is always written to a subdirectory of the document root, named by a single component the request may choose from. Neither that component nor the filename can carry a path, so there is no traversal to contain after the fact.
 *
 * This mirrors {@see StaticResourceDisposition} on the write side. The read side is already covered by {@see StaticResourcePolicy}, whose first gate rejects any path carrying a component that begins with a dot — `..` included — before the filesystem is touched. That gate guards reads; a write never passes through it, which is why the filename has to be settled here.
 */
final readonly class UploadDisposition
{
    /**
     * @param bool $isAllowed Whether the upload may proceed at all. A denied disposition carries no destination.
     * @param URL|null $destinationURL The resolved file to write, or `null` when the upload is denied. Always inside the directory the policy chose; never assembled from client input.
     * @param string|null $filename The name the file is stored under, or `null` when the upload is denied. An implementation is free to choose it, though the default one accepts or refuses what the client sent rather than rewriting it — a stored name nobody asked for is harder to explain than a refusal.
     * @param int $maximumSize The largest accepted size in bytes, or `0` for no limit.
     * @param Dictionary<mixed>|null $fileAttributes Attributes applied to the written file, or `null` to leave the ones the move produced. Carries the POSIX permissions, which a deployment may need to keep permissive: a process that writes a file it cannot later read is worse than one that writes a permissive file behind an authenticated endpoint.
     * @param string|null $failureReason Why the upload was denied, in terms the client can act on. Ignored when allowed.
     */
    public function __construct(public bool $isAllowed, public ?URL $destinationURL = null, public ?string $filename = null, public int $maximumSize = 0, public ?Dictionary $fileAttributes = null, public ?string $failureReason = null)
    {
    }
}
