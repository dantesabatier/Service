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
 * @internal
 */
class UploadsEnumerator extends DirectoryEnumerator
{
    public int $count {
        get => count($_FILES);
    }
    public bool $isEmpty {
        get => $this->count === 0;
    }
    private ?URL $currentURL = null;

    /**
     * @param URL $directoryURL
     * @param Set<string>|null $keys
     */
    public function __construct(public readonly URL $directoryURL, public readonly ?Set $keys = null)
    {
    }

    #[Override]
    public function directoryAttributes(): ?Dictionary
    {
        try {
            return FileManager::default()->attributesOfItem($this->directoryURL->path);
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
    public function getIterator(): Traversable
    {
        return (function (): Generator {
            $fileManager = FileManager::default();
            $keys = $this->keys;
            foreach ($_FILES as $file) {
                $url = $this->directoryURL->appendingPathComponent($file["name"]);
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
