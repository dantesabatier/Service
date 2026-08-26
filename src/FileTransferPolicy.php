<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\URL;

/**
 * Defines the policy used to determine how the application handles file uploads and downloads.
 *
 * The counterpart of {@see StaticResourcePolicy} for the transfer surface. Where that one is asked what to do with a resource the request named, this one is asked where a file may be written and which file may be read — and it, not the responder, resolves the destination.
 *
 * Both methods take the subdirectory and the filename as separate components, never as a path. That is the whole of the containment: a component cannot describe a location outside the directory it names, so the resolved URL is inside the document root by construction and no path has to be canonicalized or prefix-checked afterwards.
 *
 * Implementations may decide, for an upload:
 * - which subdirectories accept files, and under what name each is stored
 * - the largest size accepted
 * - the attributes the written file carries
 *
 * and, for a download:
 * - which files may be read at all
 * - whether one that lives outside the public directories may still be served
 *
 * This interface is intended to be implemented by framework users to customize transfer behavior.
 */
interface FileTransferPolicy
{
    /**
     * Evaluates one uploaded file and returns the disposition describing how it should be handled.
     *
     * @param string $directory The subdirectory the request asked for, as a single path component.
     * @param string $filename The name the client sent, unchecked. An implementation must not trust it: it arrives from `$_FILES` and may carry separators or dot components.
     * @param int $size The size of the uploaded file in bytes, as reported by the transport.
     * @return UploadDisposition The disposition describing how the upload should be handled.
     */
    public function evaluateUpload(string $directory, string $filename, int $size): UploadDisposition;

    /**
     * Resolves the directory a transfer of either kind operates in, or `null` when the request named one the policy does not accept.
     *
     * The framework never assembles this location itself, which is what keeps the root fixed: an implementation is free to place the directory wherever it likes, but the request only ever chooses among what the implementation offers.
     *
     * @param string $directory The subdirectory the request asked for, as a single path component.
     */
    public function directoryURL(string $directory): ?URL;

    /**
     * Evaluates one requested download and returns the disposition describing how it should be handled.
     *
     * @param string $directory The subdirectory the request asked for, as a single path component.
     * @param string $filename The name the request asked for, as a single path component.
     * @return DownloadDisposition The disposition describing how the download should be handled.
     */
    public function evaluateDownload(string $directory, string $filename): DownloadDisposition;
}
