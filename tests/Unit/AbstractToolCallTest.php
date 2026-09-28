<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Exception;
use Override;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Throwable;

final class AbstractToolCallTest extends TestCase
{
    /** @throws Exception */
    #[Test]
    public function aToolOverridingExecuteIsAuthorizedBeforeItRuns(): void
    {
        $tool = $this->tool(ExecuteCallProbeTool::class);
        $this->assertSame("execute", $tool->call(new Dictionary())->first?->text);
        $this->assertSame(["authorize", "execute"], $tool->steps->array);
    }

    /** @throws Exception */
    #[Test]
    public function aToolOverridingExecuteCoreIsAuthorizedBeforeItRuns(): void
    {
        $tool = $this->tool(CoreCallProbeTool::class);
        $this->assertSame("executeCore", $tool->call(new Dictionary())->first?->text);
        $this->assertSame(["authorize", "executeCore"], $tool->steps->array);
    }

    /** @throws Exception */
    #[Test]
    #[IgnoreDeprecations]
    public function callingExecuteOnAToolOverridingExecuteCoreIsDeprecatedAndStillAuthorized(): void
    {
        $tool = $this->tool(CoreCallProbeTool::class);
        $this->expectUserDeprecationMessage(AbstractTool::class . "::execute() is deprecated, use call() instead");
        $this->assertSame("executeCore", $tool->execute(new Dictionary())->first?->text);
        $this->assertSame(["authorize", "executeCore"], $tool->steps->array);
    }

    /** @throws Exception */
    #[Test]
    public function aToolOverridingNeitherFailsNamingWhatToImplement(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessageIsOrContains(EmptyCallProbeTool::class . " must implement executeCore().");
        $this->tool(EmptyCallProbeTool::class)->call(new Dictionary());
    }

    /** @throws Throwable */
    #[Test]
    public function theRegistryInvokesAToolThroughCall(): void
    {
        $tool = $this->tool(CoreCallProbeTool::class);
        $this->assertFalse(new ToolRegistry(new ArrayClass([$tool]))->call("call_probe", new Dictionary())->isError);
        $this->assertSame(["authorize", "executeCore"], $tool->steps->array);
    }

    /**
     * @template T of CallProbeTool
     * @param class-string<T> $toolClass
     * @return T
     * @throws ReflectionException
     * @noinspection PhpIncompatibleReturnTypeInspection
     */
    private function tool(string $toolClass): CallProbeTool
    {
        return new ReflectionClass($toolClass)->newInstanceWithoutConstructor();
    }
}

abstract class CallProbeTool extends AbstractTool
{
    /** @var ArrayClass<string> */
    public ArrayClass $steps {
        get => $this->steps ??= new ArrayClass();
    }
    #[Override]
    public string $name {
        get => "call_probe";
    }
    #[Override]
    public array $inputSchema {
        get => ["type" => "object"];
    }

    #[Override]
    public function authorizationResource(Dictionary $arguments): ?string
    {
        return null;
    }

    #[Override]
    public function authorizationAction(Dictionary $arguments): AuthorizationType
    {
        $this->steps->append("authorize");
        return AuthorizationType::read;
    }

    /** @return ArrayClass<ContentItem> */
    protected function record(string $step): ArrayClass
    {
        $this->steps->append($step);
        return new ArrayClass([new ContentItem("text", $step)]);
    }
}

final class ExecuteCallProbeTool extends CallProbeTool
{
    /** @return ArrayClass<ContentItem> */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        return $this->record("execute");
    }
}

final class CoreCallProbeTool extends CallProbeTool
{
    /** @return ArrayClass<ContentItem> */
    #[Override]
    protected function executeCore(Dictionary $arguments): ArrayClass
    {
        return $this->record("executeCore");
    }
}

final class EmptyCallProbeTool extends CallProbeTool
{
}
