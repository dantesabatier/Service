<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\AggregateTool;
use Sabatier\Service\MCP\Tools\CountTool;
use Sabatier\Service\MCP\Tools\CreateTool;
use Sabatier\Service\MCP\Tools\DeleteTool;
use Sabatier\Service\MCP\Tools\FetchTool;
use Sabatier\Service\MCP\Tools\GroupByTool;
use Sabatier\Service\MCP\Tools\UpdateTool;

final class ToolAuthorizationResourceTest extends TestCase
{
    /** @return iterable<string, array{class-string<AbstractTool>, AuthorizationType}> */
    public static function dataTools(): iterable
    {
        yield "fetch" => [FetchTool::class, AuthorizationType::read];
        yield "count" => [CountTool::class, AuthorizationType::read];
        yield "aggregate" => [AggregateTool::class, AuthorizationType::read];
        yield "group_by" => [GroupByTool::class, AuthorizationType::read];
        yield "create" => [CreateTool::class, AuthorizationType::create];
        yield "update" => [UpdateTool::class, AuthorizationType::update];
        yield "delete" => [DeleteTool::class, AuthorizationType::delete];
    }

    /**
     * @param class-string<AbstractTool> $toolClass
     * @throws ReflectionException
     */
    #[Test]
    #[DataProvider("dataTools")]
    public function aDataToolIsAuthorizedAgainstTheEntityItNames(string $toolClass, AuthorizationType $action): void
    {
        $tool = new ReflectionClass($toolClass)->newInstanceWithoutConstructor();
        new ReflectionProperty(AbstractTool::class, "arguments")->setValue($tool, new Dictionary(["entityName" => "Order"]));
        $this->assertSame("Order", $tool->authorizationResource);
        $this->assertSame($action, $tool->authorizationAction);
    }
}
