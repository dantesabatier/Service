<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Sabatier\Service\MCP\Tools\ToolVocabulary;

/**
 * Exercises the fallback cascade of `ToolVocabulary::localize` in isolation. The bundle-loading
 * path (`forBundle`) touches disk and is covered end-to-end against the running MCP server; here
 * we drive the resolution logic directly by seeding the `tools` (current locale) and `baseTools`
 * (base `en`) maps via reflection, matching the reflection-based construction used elsewhere in
 * the suite.
 */
final class ToolVocabularyTest extends TestCase
{
    /**
     * @param array<string, mixed> $localized
     * @param array<string, mixed> $base
     * @throws ReflectionException
     */
    private function make(array $localized, array $base): ToolVocabulary
    {
        $vocabulary = new ReflectionClass(ToolVocabulary::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(ToolVocabulary::class, "tools")->setValue($vocabulary, $localized);
        new ReflectionProperty(ToolVocabulary::class, "baseTools")->setValue($vocabulary, $base);
        return $vocabulary;
    }

    /** @throws ReflectionException */
    #[Test]
    public function returnsLocalizedValueWhenPresent(): void
    {
        $vocabulary = $this->make(
            ["count" => ["title" => "Contar", "description" => "Cuenta filas."]],
            ["count" => ["title" => "Count", "description" => "Count rows."]]
        );
        $this->assertSame("Contar", $vocabulary->localize("count", "title"));
        $this->assertSame("Cuenta filas.", $vocabulary->localize("count", "description"));
    }

    /** @throws ReflectionException */
    #[Test]
    public function fallsBackToBaseWhenLocaleLacksTheField(): void
    {
        $vocabulary = $this->make(
            ["count" => ["title" => "Contar"]],
            ["count" => ["title" => "Count", "description" => "Count rows."]]
        );
        $this->assertSame("Count rows.", $vocabulary->localize("count", "description"), "description sin traducir cae al base en");
    }

    /** @throws ReflectionException */
    #[Test]
    public function fallsBackToBaseWhenLocaleLacksTheTool(): void
    {
        $vocabulary = $this->make(
            [],
            ["count" => ["title" => "Count", "description" => "Count rows."]]
        );
        $this->assertSame("Count", $vocabulary->localize("count", "title"));
        $this->assertSame("Count rows.", $vocabulary->localize("count", "description"));
    }

    /** @throws ReflectionException */
    #[Test]
    public function returnsNullWhenNeitherLocaleNorBaseHasTheTool(): void
    {
        $vocabulary = $this->make([], []);
        $this->assertNull($vocabulary->localize("count", "title"));
        $this->assertNull($vocabulary->localize("count", "description"));
    }

    /** @throws ReflectionException */
    #[Test]
    public function returnsNullWhenEntryExistsButFieldIsMissingEverywhere(): void
    {
        $vocabulary = $this->make(
            ["count" => ["description" => "Cuenta filas."]],
            ["count" => ["description" => "Count rows."]]
        );
        $this->assertNull($vocabulary->localize("count", "title"));
    }
}
