<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Set;
use Sabatier\Service\Authorizable;
use Sabatier\Service\Authorization;
use Sabatier\Service\AuthorizationScope;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\InterfaceImplementorResolver;

class ResolverUserFixture extends ManagedObject implements Authorizable
{
    public string $username { get => ""; }
    public ?string $password { get => null; }
    public bool $isEnabled { get => true; }
    public int $refreshTokenVersion { get => 1; set {} }
    public Set $roles { get => new Set(); }

    #[Override]
    public static function defaultRepresentation(): Dictionary
    {
        return new Dictionary();
    }
}

final class ResolverOtherUserFixture extends ResolverUserFixture
{
}

final class ResolverAuthorizationFixture extends ManagedObject implements Authorization
{
    public string $name { get => ""; }
    public AuthorizationType $type { get => AuthorizationType::read; }
    public AuthorizationScope $scope { get => AuthorizationScope::all; }
}

final class ResolverPlainEntityFixture extends ManagedObject
{
}

final class InterfaceImplementorResolverTest extends TestCase
{
    #[Test]
    public function resolvesTheEntityClassImplementingAnInterface(): void
    {
        $resolver = $this->resolver(["User" => ResolverUserFixture::class, "Permission" => ResolverAuthorizationFixture::class]);
        $this->assertSame(ResolverUserFixture::class, $resolver->resolve(Authorizable::class));
        $this->assertSame(ResolverAuthorizationFixture::class, $resolver->resolve(Authorization::class));
    }

    #[Test]
    public function ignoresEntitiesThatImplementNeitherInterface(): void
    {
        $resolver = $this->resolver(["Note" => ResolverPlainEntityFixture::class, "User" => ResolverUserFixture::class]);
        $this->assertSame(ResolverUserFixture::class, $resolver->resolve(Authorizable::class));
    }

    #[Test]
    public function theFirstImplementorEncounteredWins(): void
    {
        $resolver = $this->resolver(["User" => ResolverUserFixture::class, "Admin" => ResolverOtherUserFixture::class]);
        $this->assertSame(ResolverUserFixture::class, $resolver->resolve(Authorizable::class));
    }

    #[Test]
    public function skipsEntitiesWithoutAManagedObjectClassName(): void
    {
        $resolver = $this->resolver(["Ghost" => "", "User" => ResolverUserFixture::class]);
        $this->assertSame(ResolverUserFixture::class, $resolver->resolve(Authorizable::class));
    }

    #[Test]
    public function skipsEntitiesNamingAClassThatDoesNotExist(): void
    {
        $resolver = $this->resolver(["Ghost" => "App\\Models\\Nope", "User" => ResolverUserFixture::class]);
        $this->assertSame(ResolverUserFixture::class, $resolver->resolve(Authorizable::class));
    }

    #[Test]
    public function theIndexIsBuiltOnceAndReusedAcrossResolutions(): void
    {
        $resolver = $this->resolver(["User" => ResolverUserFixture::class]);
        $index = new ReflectionProperty(InterfaceImplementorResolver::class, "index");
        $this->assertSame($index->getValue($resolver), $index->getValue($resolver));
    }

    #[Test]
    public function theIndexHoldsOnlyTheTwoInterfacesItTargets(): void
    {
        $resolver = $this->resolver(["User" => ResolverUserFixture::class, "Permission" => ResolverAuthorizationFixture::class]);
        /** @var Dictionary<string> $index */
        $index = new ReflectionProperty(InterfaceImplementorResolver::class, "index")->getValue($resolver);
        $this->assertSame([Authorizable::class, Authorization::class], $index->keys->array);
    }

    #[Test]
    public function anUnimplementedInterfaceIsAFatalMisconfiguration(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->resolver(["Note" => ResolverPlainEntityFixture::class])->resolve(Authorizable::class);
    }

    #[Test]
    public function anEmptyModelResolvesNothing(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->resolver([])->resolve(Authorization::class);
    }

    /** @param array<string, string> $entities */
    private function resolver(array $entities): InterfaceImplementorResolver
    {
        $model = new ManagedObjectModel();
        $model->entities = new Dictionary($entities)->reduce(new ArrayClass(), function (ArrayClass $descriptions, string $className, string $name): ArrayClass {
            $entity = new EntityDescription();
            $entity->name = $name;
            $entity->managedObjectClassName = $className;
            $descriptions->append($entity);
            return $descriptions;
        });
        return new InterfaceImplementorResolver($model);
    }
}
