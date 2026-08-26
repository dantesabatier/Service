<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\string_split_trimmed;

/** @internal */
final class DefaultFileTransferPolicy implements FileTransferPolicy
{
    /** @var Set<string>|null Subdirectories declared through FILE_TRANSFER_DIRECTORIES, or `null` when the variable is unset and any valid component is accepted. */
    private ?Set $allowedDirectories {
        get {
            if (isset($this->allowedDirectories)) {
                return $this->allowedDirectories;
            }
            $configured = string_split_trimmed((string)ProcessInfo::processInfo()->environment[FileTransferDirectoriesKey]);
            return $this->allowedDirectories = $configured === [] ? null : new Set($configured);
        }
    }
    /** @var Set<string>|null Extensions declared through FILE_TRANSFER_ALLOWED_EXTENSIONS, lowercased, or `null` when the variable is unset and the extension is not restricted. */
    private ?Set $allowedExtensions {
        get {
            if (isset($this->allowedExtensions)) {
                return $this->allowedExtensions;
            }
            $configured = new ArrayClass(string_split_trimmed((string)ProcessInfo::processInfo()->environment[FileTransferAllowedExtensionsKey]))->map(fn(string $extension): string => mb_strtolower(ltrim($extension, ".")));
            return $this->allowedExtensions = $configured->isEmpty ? null : new Set($configured);
        }
    }
    private int $maximumSize {
        get => $this->maximumSize ??= (int)(ProcessInfo::processInfo()->environment[FileTransferMaximumSizeKey] ?? FileTransferMaximumSizeDefault);
    }
    /** @var Dictionary<mixed> The attributes an uploaded file is written with. */
    private Dictionary $fileAttributes {
        get => $this->fileAttributes ??= new Dictionary([FileAttributeKey::posixPermissions => $this->filePermissions]);
    }
    private int $filePermissions {
        get {
            if (isset($this->filePermissions)) {
                return $this->filePermissions;
            }
            $configured = trim((string)ProcessInfo::processInfo()->environment[FileTransferFilePermissionsKey]);
            return $this->filePermissions = $configured === "" ? FileTransferFilePermissionsDefault : (int)octdec($configured);
        }
    }

    #[Override]
    public function evaluateUpload(string $directory, string $filename, int $size): UploadDisposition
    {
        $directoryURL = $this->directoryURL($directory);
        if ($directoryURL === null) {
            return new UploadDisposition(false, failureReason: "The upload directory is not accepted.");
        }
        $component = new FileTransferComponent($filename);
        if (!$component->isValid) {
            return new UploadDisposition(false, failureReason: "The filename \"$component->value\" is not accepted.");
        }
        $name = $component->value;
        $extensions = $this->allowedExtensions;
        if ($extensions !== null) {
            $extension = mb_strtolower(URL::fileURL($name)->pathExtension);
            if (!$extensions->contains(fn(string $allowed): bool => $allowed === $extension)) {
                return new UploadDisposition(false, failureReason: "Files of this type are not accepted.");
            }
        }
        $maximumSize = $this->maximumSize;
        if ($maximumSize > 0 && $size > $maximumSize) {
            return new UploadDisposition(false, failureReason: "The file is larger than the accepted maximum of $maximumSize bytes.");
        }
        return new UploadDisposition(true, $directoryURL->appendingPathComponent($name), $name, $maximumSize, $this->fileAttributes);
    }

    #[Override]
    public function evaluateDownload(string $directory, string $filename): DownloadDisposition
    {
        $directoryURL = $this->directoryURL($directory);
        if ($directoryURL === null) {
            return new DownloadDisposition(false, failureReason: "The download directory is not accepted.");
        }
        $component = new FileTransferComponent($filename);
        if (!$component->isValid) {
            return new DownloadDisposition(false, failureReason: "The filename \"$component->value\" is not accepted.");
        }
        $resourceURL = $directoryURL->appendingPathComponent($component->value);
        $disposition = Application::shared()->staticResourcePolicy->evaluate($resourceURL);
        return $disposition->shouldHandle ? new DownloadDisposition(true, $resourceURL, $disposition->isProtectedContentAvailable) : new DownloadDisposition(false, failureReason: "The requested file is not available.");
    }

    #[Override]
    public function directoryURL(string $directory): ?URL
    {
        $component = new FileTransferComponent($directory);
        if (!$component->isValid) {
            return null;
        }
        $allowed = $this->allowedDirectories;
        if ($allowed !== null && !$allowed->contains(fn(string $configured): bool => $configured === $component->value)) {
            return null;
        }
        return FileManager::default()->documentRootDirectory->appendingPathComponent($component->value);
    }
}
