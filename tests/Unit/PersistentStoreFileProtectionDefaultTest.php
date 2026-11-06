<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Exception;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionMethod;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\CoreData\PersistentContainer;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\FileProtectionType;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Application;
use const Sabatier\CoreData\PersistentStoreFileProtectionKey;

final class PersistentStoreFileProtectionDefaultTest extends TestCase
{
    private static bool $hadLibraryDirectory = false;

    private static function libraryDirectory(): URL
    {
        return FileManager::default()->url(SearchPathDirectory::libraryDirectory);
    }

    #[Override]
    public static function setUpBeforeClass(): void
    {
        self::$hadLibraryDirectory = FileManager::default()->fileExists(self::libraryDirectory()->path);
    }

    /** @throws Exception */
    #[Override]
    public static function tearDownAfterClass(): void
    {
        if (!self::$hadLibraryDirectory) {
            FileManager::default()->removeItem(self::libraryDirectory());
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        UserDefaults::standard()->register(new Dictionary([PersistentStoreFileProtectionKey => FileProtectionType::none]));
    }

    /** @throws ReflectionException */
    private function configuredProtection(): mixed
    {
        $container = new PersistentContainer("probe", new ManagedObjectModel());
        new ReflectionMethod(Application::class, "configurePersistentStoreDescriptions")->invoke(Application::shared(), $container);
        return $container->persistentStoreDescriptions->first?->options[PersistentStoreFileProtectionKey];
    }

    /** @throws ReflectionException */
    #[Test]
    public function withoutTheDefaultTheStoreIsUnprotected(): void
    {
        $this->assertSame(FileProtectionType::none, $this->configuredProtection());
    }

    /** @throws ReflectionException */
    #[Test]
    public function theDefaultSetsTheStoreProtection(): void
    {
        UserDefaults::standard()->register(new Dictionary([PersistentStoreFileProtectionKey => FileProtectionType::complete]));
        $this->assertSame(FileProtectionType::complete, $this->configuredProtection());
    }
}
