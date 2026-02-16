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
final class UploadsEnumerator extends DirectoryEnumerator
{
    public int $count {
        get => count($_FILES);
    }
    public bool $isEmpty {
        get => $this->count === 0;
    }
    private ?URL $currentURL = null;
    public ?Dictionary $directoryAttributes {
        get {
            try {
                return FileManager::default()->attributesOfItem($this->directoryURL->path);
            } catch (Exception) {
                return null;
            }
        }
    }
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
    public int $level {
        get => 0;
    }
    public bool $isEnumeratingDirectoryPostOrder {
        get => false;
    }

    /**
     * @param URL $directoryURL
     * @param Set<string>|null $keys
     */
    public function __construct(public readonly URL $directoryURL, public readonly ?Set $keys = null)
    {
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return (function (): Generator {
            $fileAttributes = new Dictionary([FileAttributeKey::posixPermissions => 0777]);
            $directoryURL = $this->directoryURL;
            $fileManager = FileManager::default();
            if (!$fileManager->fileExists($directoryURL->path)) {
                $fileManager->createDirectory($directoryURL, true, $fileAttributes);
            }
            $keys = $this->keys;
            /** @var array{name: string, tmp_name: string} $file */
            foreach ($_FILES as $file) {
                $name = $file["name"];
                $url = $directoryURL->appendingPathComponent($name);
                $destination = $url->path;
                if ($fileManager->fileExists($destination)) {
                    $fileManager->removeItem($url);
                }
                $source = $file["tmp_name"] ?? throw new BadRequestException();
                $fileManager->moveItem(URL::fileURL($source), $url) ?: throw new InternalServerErrorException();
                $fileManager->setAttributes($fileAttributes, $destination);
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
