<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use JsonException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\ToolResolver;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\ToolRegistry;

final class UntitledMCPToolFixture extends AbstractTool
{
    public string $name {
        get => "untitled_fixture";
    }
    public array $inputSchema {
        get => ["type" => "object"];
    }

    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        return new ArrayClass();
    }
}

final class MCPToolTitleTest extends TestCase
{
    /**
     * @throws JsonException
     */
    #[Test]
    public function everyBuiltInToolHasATitleInEveryVocabulary(): void
    {
        $classes = new ReflectionClass(ToolResolver::class)->getReflectionConstant("builtInToolClasses")?->getValue();
        $this->assertIsArray($classes);
        $names = new ArrayClass($classes)->map(fn(string $class): string => new ReflectionClass($class)->newInstanceWithoutConstructor()->name);
        foreach (["en", "es"] as $language) {
            $path = dirname(__DIR__, 2) . "/Resources/$language/mcp_vocabulary.json";
            $tools = json_decode((string)file_get_contents($path), true, flags: JSON_THROW_ON_ERROR)["tools"];
            foreach ($names as $name) {
                $this->assertNotEmpty($tools[$name]["title"] ?? null, "$name has no title in the $language MCP vocabulary.");
            }
        }
    }

    /** @throws ReflectionException */
    #[Test]
    public function customToolWithoutVocabularyUsesItsNameAsTitle(): void
    {
        /** @var AbstractTool $tool */
        $tool = new ReflectionClass(UntitledMCPToolFixture::class)->newInstanceWithoutConstructor();
        $descriptor = new ToolRegistry(new ArrayClass([$tool]))->list->first;
        $this->assertSame("untitled_fixture", $descriptor->jsonSerialize()["title"]);
    }
}
