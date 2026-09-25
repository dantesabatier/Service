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
use Sabatier\Service\MCP\BaseInstructionsBuilder;
use Sabatier\Service\MCP\MCPInstructionsProvider;

final class MCPInstructionsTest extends TestCase
{
    /** @var ArrayClass<URL> */
    private ArrayClass $writtenURLs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writtenURLs = new ArrayClass();
    }

    protected function tearDown(): void
    {
        $this->writtenURLs->forEach(fn(URL $url) => FileManager::default()->removeItem($url));
        new ReflectionProperty(ProcessInfo::class, "processInfo")->setValue(null, null);
        parent::tearDown();
    }

    #[Test]
    public function theFrameworkRulesStandAloneWithoutDomainInstructions(): void
    {
        $built = new BaseInstructionsBuilder()->build(null);
        $this->assertStringContainsString("CRITICAL", $built);
        $this->assertStringContainsString("describe_model", $built);
    }

    #[Test]
    public function anEmptyStringIsTreatedAsNoDomainInstructions(): void
    {
        // Joining an empty string would still prepend the blank line that separates the two parts.
        $this->assertStringStartsWith("CRITICAL", new BaseInstructionsBuilder()->build(""));
    }

    #[Test]
    public function whitespaceAloneIsTreatedAsNoDomainInstructions(): void
    {
        $this->assertStringStartsWith("CRITICAL", new BaseInstructionsBuilder()->build("   \n  "));
    }

    #[Test]
    public function domainInstructionsComeBeforeTheFrameworkRules(): void
    {
        $built = new BaseInstructionsBuilder()->build("Talk to the warehouse in Spanish.");
        $this->assertStringStartsWith("Talk to the warehouse in Spanish.", $built);
        $this->assertStringContainsString("CRITICAL", $built);
    }

    #[Test]
    public function domainInstructionsAreTrimmedBeforeBeingJoined(): void
    {
        $this->assertStringStartsWith("Domain rule.", new BaseInstructionsBuilder()->build("\n\n  Domain rule.  \n\n"));
    }

    #[Test]
    public function theFrameworkRulesAreSeparatedFromTheDomainOnesByABlankLine(): void
    {
        $this->assertStringContainsString("Domain rule.\n\nCRITICAL", new BaseInstructionsBuilder()->build("Domain rule."));
    }

    #[Test]
    public function aProviderWithoutAResourceStillAnswersWithTheFrameworkRules(): void
    {
        $this->assertStringContainsString("CRITICAL", new MCPInstructionsProvider("probe_absent_instructions.md")->build());
    }

    #[Test]
    public function theNamedResourceIsPrependedToTheFrameworkRules(): void
    {
        $this->writeInstructions("probe_instructions.md", "Ship only from the Monterrey warehouse.");
        $built = new MCPInstructionsProvider("probe_instructions.md")->build();
        $this->assertStringStartsWith("Ship only from the Monterrey warehouse.", $built);
        $this->assertStringContainsString("CRITICAL", $built);
    }

    #[Test]
    public function theLocalizedResourceIsPreferredOverTheBundleRoot(): void
    {
        $this->writeInstructions("probe_localized_instructions.md", "Root copy.");
        $this->writeInstructions("probe_localized_instructions.md", "Localized copy.", "en");
        $this->assertStringStartsWith("Localized copy.", new MCPInstructionsProvider("probe_localized_instructions.md")->build());
    }

    #[Test]
    public function theInstructionsAreBuiltOnceAndReused(): void
    {
        $filename = "probe_memoized_instructions.md";
        $this->writeInstructions($filename, "First reading.");
        $provider = new MCPInstructionsProvider($filename);
        $this->assertStringStartsWith("First reading.", $provider->build());
        // The resource is rewritten behind the provider: a second read of the file would show it.
        $this->writeInstructions($filename, "Second reading.");
        $this->assertStringStartsWith("First reading.", $provider->build());
    }

    private function writeInstructions(string $filename, string $contents, ?string $localization = null): void
    {
        $directoryURL = Bundle::main()->resourceURL ?? Bundle::main()->bundleURL;
        if ($localization) {
            $directoryURL = $directoryURL->appendingPathComponent($localization);
        }
        $url = $directoryURL->appendingPathComponent($filename);
        FileManager::default()->createFile($url->path, $contents);
        $this->writtenURLs->append($url);
    }
}
