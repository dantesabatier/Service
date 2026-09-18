<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Set;
use Sabatier\Service\AuthenticationResolver;
use Sabatier\Service\AuthenticationScheme;
use Sabatier\Service\BasicAuthentication;
use Sabatier\Service\BearerAuthentication;
use Sabatier\Service\DeletePersistentHistoryResponseStrategy;
use Sabatier\Service\DigestAuthentication;
use Sabatier\Service\FetchPersistentHistoryResponseStrategy;
use Sabatier\Service\MethodNotAllowedException;
use Sabatier\Service\PersistentHistoryResponseStrategyResolver;
use Sabatier\Service\Request;

final class ResolverRegistriesTest extends TestCase
{
    /** @var Set<string>|null */
    private ?Set $registered = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registered = AuthenticationResolver::getAuthentications();
        new ReflectionProperty(AuthenticationResolver::class, "registeredAuthenticationClasses")->setValue(null, null);
    }

    protected function tearDown(): void
    {
        new ReflectionProperty(AuthenticationResolver::class, "registeredAuthenticationClasses")->setValue(null, $this->registered);
        parent::tearDown();
    }

    #[Test]
    public function nothingIsRegisteredUntilSomethingRegisters(): void
    {
        $this->assertNull(AuthenticationResolver::getAuthentications());
    }

    #[Test]
    public function anAuthenticationSubclassIsRegistered(): void
    {
        $this->assertTrue(AuthenticationResolver::registerClass(BasicAuthentication::class));
        $this->assertTrue(AuthenticationResolver::getAuthentications()?->containsElement(BasicAuthentication::class));
    }

    #[Test]
    public function aClassThatIsNotAnAuthenticationIsRefused(): void
    {
        $this->assertFalse(AuthenticationResolver::registerClass(Set::class));
        $this->assertNull(AuthenticationResolver::getAuthentications());
    }

    #[Test]
    public function registeringTheSameClassTwiceKeepsOneEntry(): void
    {
        AuthenticationResolver::registerClass(BasicAuthentication::class);
        AuthenticationResolver::registerClass(BasicAuthentication::class);
        $this->assertSame(1, AuthenticationResolver::getAuthentications()?->count);
    }

    /** @return iterable<string, array{AuthenticationScheme, class-string}> */
    public static function schemeProvider(): iterable
    {
        yield "basic" => [AuthenticationScheme::basic, BasicAuthentication::class];
        yield "bearer" => [AuthenticationScheme::bearer, BearerAuthentication::class];
        yield "digest" => [AuthenticationScheme::digest, DigestAuthentication::class];
    }

    /** @param class-string $expected */
    #[Test]
    #[DataProvider("schemeProvider")]
    public function eachSchemeResolvesToTheClassThatSupportsIt(AuthenticationScheme $scheme, string $expected): void
    {
        $classes = new Set([BasicAuthentication::class, BearerAuthentication::class, DigestAuthentication::class]);
        $this->assertSame($expected, AuthenticationResolver::getAuthenticationClass($classes, $scheme));
    }

    #[Test]
    public function aSchemeNoRegisteredClassSupportsResolvesToNothing(): void
    {
        $this->assertNull(AuthenticationResolver::getAuthenticationClass(new Set([BasicAuthentication::class]), AuthenticationScheme::bearer));
    }

    #[Test]
    public function anEmptyRegistryResolvesToNothing(): void
    {
        $this->assertNull(AuthenticationResolver::getAuthenticationClass(new Set(), AuthenticationScheme::basic));
    }

    #[Test]
    public function anUnregisteredClassIsNoLongerOffered(): void
    {
        AuthenticationResolver::registerClass(BasicAuthentication::class);
        AuthenticationResolver::registerClass(BearerAuthentication::class);
        new AuthenticationResolver()->unregisterClass(BasicAuthentication::class);
        $registered = AuthenticationResolver::getAuthentications();
        $this->assertFalse($registered?->containsElement(BasicAuthentication::class));
        $this->assertTrue($registered?->containsElement(BearerAuthentication::class));
    }

    #[Test]
    public function unregisteringBeforeAnythingWasRegisteredIsHarmless(): void
    {
        new AuthenticationResolver()->unregisterClass(BasicAuthentication::class);
        $this->assertNull(AuthenticationResolver::getAuthentications());
    }

    #[Test]
    public function aHistoryReadResolvesToTheFetchStrategy(): void
    {
        $this->assertInstanceOf(FetchPersistentHistoryResponseStrategy::class, $this->historyResolver(HTTPRequestMethod::get)->strategy);
    }

    #[Test]
    public function aHistoryPurgeResolvesToTheDeleteStrategy(): void
    {
        $this->assertInstanceOf(DeletePersistentHistoryResponseStrategy::class, $this->historyResolver(HTTPRequestMethod::delete)->strategy);
    }

    /** @return iterable<string, array{string}> */
    public static function unsupportedHistoryMethodProvider(): iterable
    {
        yield "POST" => [HTTPRequestMethod::post];
        yield "PATCH" => [HTTPRequestMethod::patch];
        yield "PUT" => [HTTPRequestMethod::put];
    }

    #[Test]
    #[DataProvider("unsupportedHistoryMethodProvider")]
    public function historyServesNeitherWritesNorUpdates(string $method): void
    {
        $this->expectException(MethodNotAllowedException::class);
        $this->historyResolver($method)->strategy;
    }

    #[Test]
    public function everyReadBuildsAFreshHistoryStrategy(): void
    {
        $resolver = $this->historyResolver(HTTPRequestMethod::get);
        $this->assertNotSame($resolver->strategy, $resolver->strategy);
    }

    private function historyResolver(string $method): PersistentHistoryResponseStrategyResolver
    {
        $request = new ReflectionClass(Request::class)->newInstanceWithoutConstructor();
        $request->httpMethod = $method;
        new ReflectionProperty(Request::class, "parameters")->setValue($request, new Dictionary());
        return new PersistentHistoryResponseStrategyResolver($request, new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor());
    }
}
