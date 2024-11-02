<?php

namespace Sabatier\Service;

use Exception;
use Generator;
use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\DirectoryEnumerator;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Traversable;

/**
 * @extends DirectoryEnumerator<URL>
 */
class UploadsEnumerator extends DirectoryEnumerator
{
    public bool $isEmpty;
    public ?URL $currentURL = null;

    /**
     * @param URL $url
     * @param Set<string>|null $keys
     */
    public function __construct(public readonly URL $url, private readonly ?Set $keys = null)
    {
        $this->isEmpty = count($_FILES) === 0;
    }

    #[Override]
    public function directoryAttributes(): ?Dictionary
    {
        try {
            return FileManager::default()->attributesOfItem($this->url->path);
        } catch (Exception) {
            return null;
        }
    }

    #[Override]
    public function fileAttributes(): ?Dictionary
    {
        if (!($currentURL = $this->currentURL)) {
            return null;
        }
        try {
            return FileManager::default()->attributesOfItem($currentURL->path);
        } catch (Exception) {
            return null;
        }
    }

    #[Override]
    public function level(): int
    {
        return 0;
    }

    #[Override]
    public function skipDescendants(): void
    {
    }

    #[Override]
    public function isEnumeratingDirectoryPostOrder(): bool
    {
        return false;
    }

    public function getIterator(): Traversable
    {
        return (function (): Generator {
            $fileManager = FileManager::default();
            $keys = $this->keys;
            foreach ($_FILES as $file) {
                $url = $this->url->appendingPathComponent($file["name"]);
                $path = $url->path;
                if ($fileManager->fileExists($path)) {
                    $fileManager->removeItem($url);
                }
                $fileManager->moveItem(URL::fileURL($file["tmp_name"]), $url) ?: throw new InternalServerErrorException();
                $fileManager->setAttributes(new Dictionary([FileAttributeKey::posixPermissions => 0777]), $path);
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
}
