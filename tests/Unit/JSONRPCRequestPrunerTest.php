<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\JSONRPCRequestPruner;

final class JSONRPCRequestPrunerTest extends TestCase
{
    // --- Non-Dictionary values are removed ---

    #[Test]
    public function prunesParamsWhenValueIsAString(): void
    {
        $params = new Dictionary(["method" => "tools/list", "params" => "not-a-dict"]);
        new JSONRPCRequestPruner()->prune($params);
        $this->assertNull($params["params"]);
        $this->assertSame("tools/list", $params["method"]);
    }

    #[Test]
    public function prunesArgumentsWhenValueIsAnInteger(): void
    {
        $params = new Dictionary(["method" => "ping", "arguments" => 42]);
        new JSONRPCRequestPruner()->prune($params);
        $this->assertNull($params["arguments"]);
    }

    #[Test]
    public function prunesArgumentsWhenValueIsAnArray(): void
    {
        $params = new Dictionary(["arguments" => ["a", "b"]]);
        new JSONRPCRequestPruner()->prune($params);
        $this->assertNull($params["arguments"]);
    }

    // --- Dictionary values are kept ---

    #[Test]
    public function keepsDictionaryParams(): void
    {
        $inner = new Dictionary(["name" => "echo"]);
        $params = new Dictionary(["params" => $inner]);
        new JSONRPCRequestPruner()->prune($params);
        $this->assertSame($inner, $params["params"]);
    }

    #[Test]
    public function keepsDictionaryArguments(): void
    {
        $inner = new Dictionary(["x" => 1]);
        $params = new Dictionary(["arguments" => $inner]);
        new JSONRPCRequestPruner()->prune($params);
        $this->assertSame($inner, $params["arguments"]);
    }

    // --- Missing keys are left untouched ---

    #[Test]
    public function doesNotThrowWhenKeyIsAbsent(): void
    {
        $params = new Dictionary(["method" => "ping", "id" => 1]);
        new JSONRPCRequestPruner()->prune($params);
        $this->assertSame("ping", $params["method"]);
    }

    // --- Custom key list ---

    #[Test]
    public function respectsCustomKeyList(): void
    {
        $params = new Dictionary(["data" => "not-a-dict", "params" => "also-not"]);
        new JSONRPCRequestPruner(["data"])->prune($params);
        $this->assertNull($params["data"]);
        $this->assertSame("also-not", $params["params"]); // not in custom list, untouched
    }
}
