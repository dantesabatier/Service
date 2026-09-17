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
use Sabatier\Service\MCP\Schema\PredicateGuideFactory;
use const Sabatier\Service\MCPPredicateExamplesFilenameDefault;
use const Sabatier\Service\MCPPredicateExamplesFilenameKey;

final class PredicateGuideFactoryTest extends TestCase
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
            $this->resetProcessInfo();
            $this->envWritten = false;
        }
        parent::tearDown();
    }

    #[Test]
    public function describesEveryFormatPlaceholderTheAdapterAccepts(): void
    {
        $guide = new PredicateGuideFactory()->make();
        $this->assertSame(["%K", "%s", "%@", "%d", "%f"], array_keys($guide->placeholders));
        $this->assertStringContainsString("Key path", $guide->placeholders["%K"]);
    }

    #[Test]
    public function listsTheComparisonAndLogicalOperators(): void
    {
        $operators = new ArrayClass(new PredicateGuideFactory()->make()->operators);
        new ArrayClass(["=", "!=", "<", ">", "<=", ">=", "CONTAINS[cd]", "LIKE[cd]", "IN", "BETWEEN", "AND", "OR", "NOT"])->forEach(fn(string $operator) => $this->assertTrue($operators->containsElement($operator), "missing operator $operator"));
    }

    #[Test]
    public function alwaysCarriesTheBaseExamples(): void
    {
        $examples = new ArrayClass(new PredicateGuideFactory()->make()->examples);
        $this->assertSame(8, $examples->count);
        $this->assertNotNull($examples->first(fn(string $example): bool => str_contains($example, "BETWEEN")));
        $this->assertNotNull($examples->first(fn(string $example): bool => str_contains($example, "IN list")));
    }

    #[Test]
    public function appendsTheExamplesFoundInTheBundleResource(): void
    {
        $this->writeExamples("predicate_guide_fixture.json", "[\"Fixture: predicate=\\\"%K = %s\\\"\"]");
        $examples = new ArrayClass(new PredicateGuideFactory()->make()->examples);
        $this->assertSame(9, $examples->count);
        $this->assertStringContainsString("Fixture:", (string)$examples->last);
    }

    #[Test]
    public function fallsBackToTheBaseExamplesWhenTheResourceIsAbsent(): void
    {
        $this->useExamplesFilename("predicate_guide_absent.json");
        $this->assertSame(8, new ArrayClass(new PredicateGuideFactory()->make()->examples)->count);
    }

    #[Test]
    public function fallsBackToTheBaseExamplesWhenTheResourceIsEmpty(): void
    {
        $this->writeExamples("predicate_guide_empty.json", "");
        $this->assertSame(8, new ArrayClass(new PredicateGuideFactory()->make()->examples)->count);
    }

    #[Test]
    public function fallsBackToTheBaseExamplesWhenTheResourceIsNotValidJSON(): void
    {
        $this->writeExamples("predicate_guide_broken.json", "{not json");
        $this->assertSame(8, new ArrayClass(new PredicateGuideFactory()->make()->examples)->count);
    }

    #[Test]
    public function readsTheDefaultFilenameWhenTheEnvironmentNamesNone(): void
    {
        $url = (Bundle::main()->resourceURL ?? Bundle::main()->bundleURL)->appendingPathComponent(MCPPredicateExamplesFilenameDefault);
        FileManager::default()->createFile($url->path, "[\"Default filename copy\"]");
        $this->writtenURLs->append($url);
        $this->resetProcessInfo();
        $examples = new ArrayClass(new PredicateGuideFactory()->make()->examples);
        $this->assertSame(9, $examples->count);
        $this->assertStringContainsString("Default filename copy", (string)$examples->last);
    }

    #[Test]
    public function prefersTheLocalizedResourceOverTheBundleRoot(): void
    {
        $this->writeExamples("predicate_guide_localized.json", "[\"Root copy\"]");
        $this->writeExamples("predicate_guide_localized.json", "[\"Localized copy\"]", "en");
        $examples = new ArrayClass(new PredicateGuideFactory()->make()->examples);
        $this->assertStringContainsString("Localized copy", (string)$examples->last);
    }

    private function writeExamples(string $filename, string $contents, ?string $localization = null): void
    {
        $directoryURL = Bundle::main()->resourceURL ?? Bundle::main()->bundleURL;
        if ($localization) {
            $directoryURL = $directoryURL->appendingPathComponent($localization);
        }
        $url = $directoryURL->appendingPathComponent($filename);
        FileManager::default()->createFile($url->path, $contents);
        $this->writtenURLs->append($url);
        $this->useExamplesFilename($filename);
    }

    private function useExamplesFilename(string $filename): void
    {
        $key = MCPPredicateExamplesFilenameKey;
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
