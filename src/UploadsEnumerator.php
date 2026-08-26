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
                $source = (string)($file["tmp_name"] ?? throw new BadRequestException());
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
     * A single-file field arrives with scalar members, while `name="files[]"` arrives with each member as a parallel array — the same field carrying several files. Reading `name` without telling the two apart works only for the first shape, so a multiple upload would otherwise fail on an array where a string was expected.
     *
     * @return Generator<array{name: string, tmp_name: string, size: int}>
     */
    private function uploadedFiles(): Generator
    {
        /** @var array{name: string|list<string>, tmp_name: string|list<string>, size: int|list<int>} $file */
        foreach ($_FILES as $file) {
            $names = $file["name"];
            /** @var list<string> $nameList */
            $nameList = is_array($names) ? $names : [(string)$names];
            /** @var list<string> $tmpNames */
            $tmpNames = is_array($file["tmp_name"]) ? $file["tmp_name"] : [(string)$file["tmp_name"]];
            /** @var list<int> $sizes */
            $sizes = is_array($file["size"]) ? $file["size"] : [(int)$file["size"]];
            foreach ($nameList as $index => $name) {
                yield ["name" => $name, "tmp_name" => $tmpNames[$index] ?? "", "size" => $sizes[$index] ?? 0];
            }
        }
    }
}
