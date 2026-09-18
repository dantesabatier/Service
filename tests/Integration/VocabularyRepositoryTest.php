<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\URL;
use Sabatier\Service\MCP\Schema\VocabularyRepository;
use const Sabatier\Service\MCPVocabularyFilenameDefault;
use const Sabatier\Service\MCPVocabularyFilenameKey;

final class VocabularyRepositoryTest extends TestCase
{
    /** @var ArrayClass<URL> */
    private ArrayClass $writtenURLs;
    private bool $envWritten = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writtenURLs = new ArrayClass();
    }

    protected function tearDown(): void
    {
        $this->writtenURLs->forEach(fn(URL $url) => FileManager::default()->removeItem($url));
        if ($this->envWritten) {
            FileManager::default()->removeItem($this->environmentURL());
            $this->envWritten = false;
        }
        $this->resetProcessInfo();
        parent::tearDown();
    }

    #[Test]
    public function theVocabularyTheBundleShipsIsLoaded(): void
    {
        $this->assertNotSame([], new VocabularyRepository()->load());
    }

    #[Test]
    public function aVocabularyNamedByTheEnvironmentIsPreferred(): void
    {
        $this->writeVocabulary("probe_vocabulary.json", "{\"entities\": {\"Order\": {\"description\": \"Pedido\"}}}");
        $this->assertSame("Pedido", new VocabularyRepository()->load()["entities"]["Order"]["description"]);
    }

    #[Test]
    public function aVocabularyThatIsNotThereYieldsNothing(): void
    {
        $this->useFilename("probe_absent_vocabulary.json");
        $this->assertSame([], new VocabularyRepository()->load());
    }

    #[Test]
    public function anEmptyVocabularyFileYieldsNothing(): void
    {
        $this->writeVocabulary("probe_empty_vocabulary.json", "");
        $this->assertSame([], new VocabularyRepository()->load());
    }

    #[Test]
    public function aVocabularyThatIsNotValidJSONYieldsNothing(): void
    {
        $this->writeVocabulary("probe_broken_vocabulary.json", "{not json");
        $this->assertSame([], new VocabularyRepository()->load());
    }

    #[Test]
    public function theLocalizedVocabularyIsPreferredOverTheBundleRoot(): void
    {
        $this->writeVocabulary("probe_localized_vocabulary.json", "{\"entities\": {\"Order\": {\"description\": \"Root\"}}}");
        $this->writeVocabulary("probe_localized_vocabulary.json", "{\"entities\": {\"Order\": {\"description\": \"Localized\"}}}", "en");
        $this->assertSame("Localized", new VocabularyRepository()->load()["entities"]["Order"]["description"]);
    }

    #[Test]
    public function theDefaultFilenameIsTheOneReadWhenTheEnvironmentNamesNone(): void
    {
        // The shipped vocabulary lives under that exact name, so reading it back is what shows the
        // default was used rather than some other file happening to be found.
        $this->resetProcessInfo();
        $shipped = FileManager::default()->contents((Bundle::main()->resourceURL ?? Bundle::main()->bundleURL)->appendingPathComponent("en")->appendingPathComponent(MCPVocabularyFilenameDefault)->path);
        $this->assertSame(json_decode((string)$shipped, true), new VocabularyRepository()->load());
    }

    private function writeVocabulary(string $filename, string $contents, ?string $localization = null): void
    {
        $directoryURL = Bundle::main()->resourceURL ?? Bundle::main()->bundleURL;
        if ($localization) {
            $directoryURL = $directoryURL->appendingPathComponent($localization);
        }
        $url = $directoryURL->appendingPathComponent($filename);
        FileManager::default()->createFile($url->path, $contents);
        $this->writtenURLs->append($url);
        $this->useFilename($filename);
    }

    private function useFilename(string $filename): void
    {
        $key = MCPVocabularyFilenameKey;
        FileManager::default()->createFile($this->environmentURL()->path, "$key=$filename\n");
        $this->envWritten = true;
        $this->resetProcessInfo();
    }

    private function environmentURL(): URL
    {
        return FileManager::default()->documentRootDirectory->appendingPathComponent(".env");
    }

    private function resetProcessInfo(): void
    {
        new ReflectionProperty(ProcessInfo::class, "processInfo")->setValue(null, null);
    }
}
