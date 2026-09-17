<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Service\AuthorizationContext;
use Sabatier\Service\CreatePersistentSpaceResponseStrategy;
use Sabatier\Service\DeletePersistentSpaceResponseStrategy;
use Sabatier\Service\FieldLevelSecurityPolicy;
use Sabatier\Service\MethodNotAllowedException;
use Sabatier\Service\PersistentSpaceResponseStrategyResolver;
use Sabatier\Service\ReadPersistentSpaceResponseStrategy;
use Sabatier\Service\Request;
use Sabatier\Service\UpdatePersistentSpaceResponseStrategy;

final class PersistentSpaceResponseStrategyResolverTest extends TestCase
{
    /** @return iterable<string, array{string, class-string}> */
    public static function methodProvider(): iterable
    {
        yield "POST creates" => [HTTPRequestMethod::post, CreatePersistentSpaceResponseStrategy::class];
        yield "GET reads" => [HTTPRequestMethod::get, ReadPersistentSpaceResponseStrategy::class];
        yield "PATCH updates" => [HTTPRequestMethod::patch, UpdatePersistentSpaceResponseStrategy::class];
        yield "DELETE deletes" => [HTTPRequestMethod::delete, DeletePersistentSpaceResponseStrategy::class];
    }

    #[Test]
    #[DataProvider("methodProvider")]
    public function eachMethodResolvesToItsOwnStrategy(string $method, string $expected): void
    {
        $this->assertInstanceOf($expected, $this->resolver($method)->strategy);
    }

    /** @return iterable<string, array{string}> */
    public static function unsupportedMethodProvider(): iterable
    {
        yield "PUT" => [HTTPRequestMethod::put];
        yield "HEAD" => [HTTPRequestMethod::head];
        yield "OPTIONS" => [HTTPRequestMethod::options];
    }

    #[Test]
    #[DataProvider("unsupportedMethodProvider")]
    public function aMethodPersistentSpaceDoesNotServeIsRefused(string $method): void
    {
        $this->expectException(MethodNotAllowedException::class);
        $this->resolver($method)->strategy;
    }

    #[Test]
    public function theStrategyCarriesTheRequestEntityAndContextItWasBuiltWith(): void
    {
        $resolver = $this->resolver(HTTPRequestMethod::get);
        $strategy = $resolver->strategy;
        $this->assertSame($resolver->request, $strategy->request);
        $this->assertSame($resolver->entity, new ReflectionProperty($strategy, "entity")->getValue($strategy));
        $this->assertSame($resolver->managedObjectContext, new ReflectionProperty($strategy, "managedObjectContext")->getValue($strategy));
    }

    #[Test]
    public function everyReadBuildsAFreshStrategy(): void
    {
        $resolver = $this->resolver(HTTPRequestMethod::get);
        $this->assertNotSame($resolver->strategy, $resolver->strategy);
    }

    private function resolver(string $method): PersistentSpaceResponseStrategyResolver
    {
        $request = new ReflectionClass(Request::class)->newInstanceWithoutConstructor();
        $request->httpMethod = $method;
        new ReflectionProperty(Request::class, "parameters")->setValue($request, new Dictionary());
        $entity = new EntityDescription();
        $entity->name = "Order";
        $policy = new FieldLevelSecurityPolicy(new AuthorizationContext(null, new ArrayClass(), false));
        return new PersistentSpaceResponseStrategyResolver($request, $entity, new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor(), $policy);
    }
}
