<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Generator;
use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\DirectoryEnumerator;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Traversable;

/**
 * @extends DirectoryEnumerator<URL>
 * @internal
 */
final class UploadsEnumerator extends DirectoryEnumerator
{
    public int $count {
        get => count($_FILES);
    }
    public bool $isEmpty {
        get => $this->count === 0;
    }
    private ?URL $currentURL = null;
    /** @var URL The directory uploads are written to, resolved by the policy from the subdirectory the request named. */
    public URL $directoryURL {
        get => $this->directoryURL ??= Application::shared()->fileTransferPolicy->directoryURL($this->directory) ?? throw new BadRequestException("The upload directory is not accepted.");
    }
    #[Override]
    public ?Dictionary $directoryAttributes {
        get {
            try {
                return FileManager::default()->attributesOfItem($this->directoryURL->path);
            } catch (Exception) {
                return null;
            }
        }
    }
    #[Override]
    public ?Dictionary $fileAttributes {
        get {
            if (!($currentURL = $this->currentURL)) {
                return null;
            }
            try {
                return FileManager::default()->attributesOfItem($currentURL->path);
            } catch (Exception) {
                return null;
            }
        }
    }
    #[Override]
    public int $level {
        get => 0;
    }
    #[Override]
    public bool $isEnumeratingDirectoryPostOrder {
        get => false;
    }

    /**
     * @param string $directory The subdirectory the request asked for, as a single path component.
     * @param Set<string>|null $keys
     */
    public function __construct(public readonly string $directory, public readonly ?Set $keys = null)
    {
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return (function (): Generator {
            $policy = Application::shared()->fileTransferPolicy;
            $fileManager = FileManager::default();
            $keys = $this->keys;
            foreach ($this->uploadedFiles() as $file) {
                $failure = self::transportFailure((int)$file["error"]);
                if ($failure !== null) {
                    throw new BadRequestException($failure);
                }
                $disposition = $policy->evaluateUpload($this->directory, (string)$file["name"], (int)$file["size"]);
                $url = $disposition->isAllowed ? $disposition->destinationURL : null;
                if ($url === null) {
                    throw new BadRequestException($disposition->failureReason ?? "The upload was not accepted.");
                }
                $attributes = $disposition->fileAttributes;
                $directoryURL = $this->directoryURL;
                if (!$fileManager->fileExists($directoryURL->path)) {
                    $fileManager->createDirectory($directoryURL, true, $attributes);
                }
                $destination = $url->path;
                if ($fileManager->fileExists($destination)) {
                    $fileManager->removeItem($url);
                }
                $source = (string)$file["tmp_name"];
                $source !== "" && is_uploaded_file($source) ?: throw new BadRequestException("The file was not received as an upload.");
                $fileManager->moveItem(URL::fileURL($source), $url) ?: throw new InternalServerErrorException();
                if ($attributes !== null) {
                    $fileManager->setAttributes($attributes, $destination);
                }
                if ($keys !== null) {
                    $values = $url->resourceValues($keys);
                    foreach ($values->allValues as $key => $value) {
                        $url->setTemporaryResourceValue($value, $key);
                    }
                }
                $this->currentURL = $url;
                yield $url;
            }
        })();
    }

    /**
     * What went wrong before the file arrived, or `null` when nothing did.
     *
     * The transport reports its own failures through `error`, and a request carrying one is answered rather than executed: a file rejected for exceeding `upload_max_filesize` arrives with a size of zero and an empty temporary path, which passes a size check and then fails to move — a `500` where the caller deserved to be told what it did wrong. The two size codes are told apart because the caller can act on one and not the other: `UPLOAD_ERR_INI_SIZE` is the server's own ceiling.
     *
     * @param int $error One of PHP's `UPLOAD_ERR_*` codes.
     */
    private static function transportFailure(int $error): ?string
    {
        return match ($error) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_INI_SIZE => "The file is larger than this server accepts.",
            UPLOAD_ERR_FORM_SIZE => "The file is larger than the form allowed.",
            UPLOAD_ERR_PARTIAL => "The file arrived incomplete. Send it again.",
            UPLOAD_ERR_NO_FILE => "No file was sent.",
            default => "The file could not be received.",
        };
    }

    /**
     * A single-file field arrives with scalar members, while `name="files[]"` arrives with each member as a parallel array — the same field carrying several files. Reading `name` without telling the two apart works only for the first shape, so a multiple upload would otherwise fail on an array where a string was expected.
     *
     * @return Generator<array{name: string, tmp_name: string, size: int, error: int}>
     */
    private function uploadedFiles(): Generator
    {
        /** @var array{name: string|list<string>, tmp_name: string|list<string>, size: int|list<int>, error: int|list<int>} $file */
        foreach ($_FILES as $file) {
            $names = $file["name"];
            /** @var list<string> $nameList */
            $nameList = is_array($names) ? $names : [(string)$names];
            /** @var list<string> $tmpNames */
            $tmpNames = is_array($file["tmp_name"]) ? $file["tmp_name"] : [(string)$file["tmp_name"]];
            /** @var list<int> $sizes */
            $sizes = is_array($file["size"]) ? $file["size"] : [(int)$file["size"]];
            $error = $file["error"] ?? UPLOAD_ERR_OK;
            /** @var list<int> $errors */
            $errors = is_array($error) ? $error : [(int)$error];
            foreach ($nameList as $index => $name) {
                yield ["name" => $name, "tmp_name" => $tmpNames[$index] ?? "", "size" => $sizes[$index] ?? 0, "error" => $errors[$index] ?? UPLOAD_ERR_OK];
            }
        }
    }
}
