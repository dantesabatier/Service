<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\CreateTool;
use Sabatier\Service\MCP\Tools\DeleteTool;
use Sabatier\Service\MCP\Tools\UpdateTool;

/**
 * Fixes the arguments each mutating tool insists on before it reaches the store, and the shape it
 * advertises to the model. Executing the mutation is not exercised: that needs a store.
 */
final class MutatingToolArgumentTest extends TestCase
{
    /** @return iterable<string, array{class-string<AbstractTool>, array<string, mixed>, string}> */
    public static function missingArgumentProvider(): iterable
    {
        yield "update without an entity" => [UpdateTool::class, ["objectID" => 1, "values" => []], "entity is required"];
        yield "update without an objectID" => [UpdateTool::class, ["entity" => "Order", "values" => []], "objectID is required"];
        yield "update without values" => [UpdateTool::class, ["entity" => "Order", "objectID" => 1], "values is required"];
        yield "delete without an entity" => [DeleteTool::class, ["objectID" => 1], "entity is required"];
        yield "delete without an objectID" => [DeleteTool::class, ["entity" => "Order"], "objectID is required"];
    }

    /**
     * @param class-string<AbstractTool> $toolClass
     * @param array<string, mixed> $arguments
     */
    #[Test]
    #[DataProvider("missingArgumentProvider")]
    public function aMissingArgumentIsRefusedBeforeTheStoreIsTouched(string $toolClass, array $arguments, string $expected): void
    {
        try {
            $this->tool($toolClass)->execute(new Dictionary($arguments));
            $this->fail("A missing argument must be refused.");
        } catch (InternalInconsistencyException $exception) {
            $this->assertStringContainsString($expected, (string)$exception->error->localizedFailureReason);
        }
    }

    /** @return iterable<string, array{class-string<AbstractTool>, string, list<string>}> */
    public static function toolShapeProvider(): iterable
    {
        yield "update" => [UpdateTool::class, "update", ["entity", "objectID", "values"]];
        yield "delete" => [DeleteTool::class, "delete", ["entity", "objectID"]];
        yield "create" => [CreateTool::class, "create", ["entity", "values"]];
    }

    /**
     * @param class-string<AbstractTool> $toolClass
     * @param list<string> $required
     */
    #[Test]
    #[DataProvider("toolShapeProvider")]
    public function eachToolAdvertisesItsNameAndRequiredArguments(string $toolClass, string $name, array $required): void
    {
        $tool = $this->tool($toolClass);
        $this->assertSame($name, $tool->name);
        $this->assertSame("object", $tool->inputSchema["type"]);
        $this->assertSame($required, $tool->inputSchema["required"]);
    }

    /** @return iterable<string, array{class-string<AbstractTool>}> */
    public static function toolProvider(): iterable
    {
        yield "update" => [UpdateTool::class];
        yield "delete" => [DeleteTool::class];
        yield "create" => [CreateTool::class];
    }

    /** @param class-string<AbstractTool> $toolClass */
    #[Test]
    #[DataProvider("toolProvider")]
    public function aMutatingToolNeverClaimsToBeReadOnly(string $toolClass): void
    {
        $tool = $this->tool($toolClass);
        $this->assertFalse($tool->isReadOnly);
        $this->assertFalse($tool->isReadOnlyCall(new Dictionary()));
    }

    /** @param class-string<AbstractTool> $toolClass */
    #[Test]
    #[DataProvider("toolProvider")]
    public function aMutatingToolStaysWithinTheModelItKnows(string $toolClass): void
    {
        $this->assertFalse($this->tool($toolClass)->isOpenWorld);
    }

    /**
     * Every mutating tool is advertised as destructive, create included: the annotation is the
     * conservative hint a client uses to decide whether to confirm, not a claim about deletion.
     *
     * @param class-string<AbstractTool> $toolClass
     */
    #[Test]
    #[DataProvider("toolProvider")]
    public function everyMutatingToolIsAdvertisedAsDestructive(string $toolClass): void
    {
        $this->assertTrue($this->tool($toolClass)->isDestructive);
    }

    /** @param class-string<AbstractTool> $toolClass */
    private function tool(string $toolClass): AbstractTool
    {
        return new $toolClass(new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor(), new ReflectionClass(ModelDescriptor::class)->newInstanceWithoutConstructor());
    }
}
