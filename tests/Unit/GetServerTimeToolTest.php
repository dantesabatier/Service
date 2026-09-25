<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Tools\GetServerTimeTool;

/**
 * Exercises get_server_time without a live store: it is an app-agnostic tool, so the call
 * needs no context and no descriptor, and the result carries the server's current date,
 * time and timezone in the shape the LLM expects.
 */
final class GetServerTimeToolTest extends TestCase
{
    private function makeTool(): GetServerTimeTool
    {
        $context = new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
        $descriptor = new ReflectionClass(ModelDescriptor::class)->newInstanceWithoutConstructor();
        return new GetServerTimeTool($context, $descriptor);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(GetServerTimeTool $tool, Dictionary $arguments): array
    {
        $content = $tool->execute($arguments);
        /** @var array<string, mixed> */
        return json_decode($content->first->text, true, flags: JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function returnsServerTimeFields(): void
    {
        $result = $this->decode($this->makeTool(), new Dictionary());
        $this->assertSame(date_default_timezone_get(), $result["timezone"]);
        $this->assertSame(date("Y-m-d"), $result["date"]);
        $this->assertSame(date("l"), $result["weekday"]);
        $this->assertLessThanOrEqual(2, abs($result["unix"] - time()));
        $this->assertMatchesRegularExpression("/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}/", $result["iso8601"]);
    }

    /**
     * Reading state is not the same question as answering the same way twice.
     *
     * This tool takes no arguments, so every call in a run shares one cache key: cached, the first
     * answer would stand as the time for the rest of the run, and the model would be told not to ask
     * again. It stays read-only — it writes nothing — and declines the cache.
     */
    #[Test]
    public function theClockIsReadOnlyButNeverCacheable(): void
    {
        $tool = $this->makeTool();

        $this->assertTrue($tool->isReadOnly);
        $this->assertFalse($tool->isCacheable, "A run that remembers the time reports a frozen clock.");
    }
}
