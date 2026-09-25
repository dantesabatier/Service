<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPCookieStringPolicy;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UUID;
use Sabatier\Service\CookieParameters;
use Sabatier\Service\Session;
use Sabatier\Service\SessionStatus;

final class SessionTest extends TestCase
{
    /**
     * Reading Session::$storageURL creates the bundle's caches tree under the project, which is not
     * a build artifact the repository ignores, so the suite takes it away again once it is done with it.
     */
    public static function tearDownAfterClass(): void
    {
        FileManager::default()->removeItem(FileManager::default()->url(SearchPathDirectory::cachesDirectory)->deletingLastPathComponent());
        parent::tearDownAfterClass();
    }

    #[Test]
    public function aFreshSessionIsNeitherStartedNorActive(): void
    {
        $session = new Session();
        $this->assertSame(SessionStatus::none, $session->status);
        $this->assertFalse($session->isActive);
    }

    #[Test]
    public function reportsThePlatformSessionName(): void
    {
        $session = new Session();
        $this->assertSame("PHPSESSID", $session->name);
    }

    #[Test]
    public function theSessionNameIsWritable(): void
    {
        $session = new Session();
        $session->name = "SABATIERSESSID";
        $this->assertSame("SABATIERSESSID", $session->name);
    }

    #[Test]
    public function theStorageDirectoryIsCreatedOnFirstAccessAndMemoized(): void
    {
        $storageURL = new Session()->storageURL;
        FileManager::default()->removeItem($storageURL);
        $this->assertFalse(FileManager::default()->fileExists($storageURL->path));
        $session = new Session();
        $this->assertTrue(FileManager::default()->fileExists($session->storageURL->path));
        $this->assertSame("Session", $session->storageURL->lastPathComponent);
        $this->assertSame($session->storageURL, $session->storageURL);
    }

    #[Test]
    public function theCookieParametersDefaultToTheRequestHostAndAreMemoized(): void
    {
        $_SERVER["HTTP_HOST"] = "example.test";
        $_SERVER["REQUEST_URI"] = "/orders";
        $session = new Session();
        $cookieParameters = $session->cookieParameters;
        $this->assertSame("example.test", $cookieParameters->domain);
        $this->assertSame("/", $cookieParameters->path);
        $this->assertSame(0, $cookieParameters->lifetime);
        $this->assertTrue($cookieParameters->isSecure);
        $this->assertTrue($cookieParameters->isHTTPOnly);
        $this->assertSame(HTTPCookieStringPolicy::sameSiteLax, $cookieParameters->sameSitePolicy);
        $this->assertSame($cookieParameters, $session->cookieParameters);
    }

    #[Test]
    public function theCookieParametersFallBackToAnEmptyDomainWithoutARequest(): void
    {
        unset($_SERVER["HTTP_HOST"], $_SERVER["REQUEST_URI"]);
        $this->assertSame("", new Session()->cookieParameters->domain);
    }

    #[Test]
    public function startingASessionMakesItActiveAndGivesItAnIdentifier(): void
    {
        $session = new Session();
        $session->start();
        $this->assertSame(SessionStatus::active, $session->status);
        $this->assertTrue($session->isActive);
        $this->assertNotSame("", $session->id);
    }

    #[Test]
    public function theSessionIsWrittenIntoItsOwnStorageDirectory(): void
    {
        $storageURL = $this->scratchStorage();
        $session = $this->sessionStoringAt($storageURL, new CookieParameters("example.test"));
        // session_save_path only takes effect while no session is active, so an earlier test's session has to be written out first.
        $session->commit();
        $session->start();
        $session->setValueForKey("Ada", "user");
        $session->commit();
        $this->assertTrue(FileManager::default()->fileExists($storageURL->appendingPathComponent("sess_$session->id")->path));
    }

    #[Test]
    public function startingAnAlreadyActiveSessionKeepsTheSameIdentifier(): void
    {
        $session = new Session();
        $session->start();
        $id = $session->id;
        $session->start();
        $this->assertSame($id, $session->id);
    }

    #[Test]
    public function writesAndReadsBackThroughKeyValueCoding(): void
    {
        $session = new Session();
        $session->start();
        $session->setValueForKey("Ada", "user");
        $this->assertSame("Ada", $session->valueForKey("user"));
    }

    #[Test]
    public function writingNullUnsetsTheKey(): void
    {
        $session = new Session();
        $session->start();
        $session->setValueForKey("Ada", "user");
        $session->setValueForKey(null, "user");
        $this->assertNull($session->valueForKey("user"));
        $this->assertArrayNotHasKey("user", $_SESSION);
    }

    #[Test]
    public function readingAnAbsentKeyYieldsNull(): void
    {
        $session = new Session();
        $session->start();
        $this->assertNull($session->valueForKey("absent"));
    }

    #[Test]
    public function resettingDiscardsWritesMadeSinceTheSessionWasRead(): void
    {
        $session = new Session();
        // reset() reinstates the values the session was read with, so the write it has to discard must come after a fresh read.
        $session->invalidate();
        $session->start();
        $session->setValueForKey("Ada", "user");
        $session->reset();
        $this->assertNull($session->valueForKey("user"));
    }

    #[Test]
    public function regeneratingTheIdentifierReplacesIt(): void
    {
        $session = new Session();
        $session->start();
        $id = $session->id;
        $session->regenerateID();
        $this->assertNotSame($id, $session->id);
        $this->assertSame(SessionStatus::active, $session->status);
    }

    #[Test]
    public function closingWritesTheSessionOutAndLeavesItInactive(): void
    {
        $session = new Session();
        $session->start();
        $session->close();
        $this->assertSame(SessionStatus::none, $session->status);
        $this->assertFalse($session->isActive);
    }

    #[Test]
    public function committingWritesTheSessionOutAndLeavesItInactive(): void
    {
        $session = new Session();
        $session->start();
        $session->commit();
        $this->assertSame(SessionStatus::none, $session->status);
    }

    #[Test]
    public function invalidatingDestroysTheSessionAndItsContents(): void
    {
        $session = new Session();
        $session->start();
        $session->setValueForKey("Ada", "user");
        $session->invalidate();
        $this->assertSame(SessionStatus::none, $session->status);
        $this->assertNull($session->valueForKey("user"));
    }

    #[Test]
    public function invalidatingAnInactiveSessionIsHarmless(): void
    {
        $session = new Session();
        $session->invalidate();
        $this->assertSame(SessionStatus::none, $session->status);
    }

    #[Test]
    public function theDestructorSweepsSessionFilesBelongingToOtherSessions(): void
    {
        $storageURL = $this->scratchStorage();
        $session = $this->sessionStoringAt($storageURL, new CookieParameters("example.test"));
        $session->start();
        $foreignURL = $storageURL->appendingPathComponent("sess_" . new UUID()->uuidString);
        $ownURL = $storageURL->appendingPathComponent("sess_$session->id");
        $unrelatedURL = $storageURL->appendingPathComponent("notes.txt");
        new ArrayClass([$foreignURL, $ownURL, $unrelatedURL])->forEach(fn(URL $url) => FileManager::default()->createFile($url->path, ""));
        unset($session);
        $this->assertFalse(FileManager::default()->fileExists($foreignURL->path));
        $this->assertTrue(FileManager::default()->fileExists($ownURL->path));
        $this->assertTrue(FileManager::default()->fileExists($unrelatedURL->path));
    }

    #[Test]
    public function theDestructorKeepsTheOwnSessionFileWhenItIsStillWithinItsLifetime(): void
    {
        $storageURL = $this->scratchStorage();
        $session = $this->sessionStoringAt($storageURL, new CookieParameters("example.test", lifetime: 3600));
        $session->start();
        $ownURL = $storageURL->appendingPathComponent("sess_$session->id");
        FileManager::default()->createFile($ownURL->path, "");
        unset($session);
        $this->assertTrue(FileManager::default()->fileExists($ownURL->path));
    }

    #[Test]
    public function theDestructorSweepsTheOwnSessionFileOnceItsLifetimeHasElapsed(): void
    {
        $storageURL = $this->scratchStorage();
        $session = $this->sessionStoringAt($storageURL, new CookieParameters("example.test", lifetime: 3600));
        $session->start();
        $ownURL = $storageURL->appendingPathComponent("sess_$session->id");
        FileManager::default()->createFile($ownURL->path, "");
        // The file is created now, so only a negative lifetime puts its expiry in the past. It is swapped in after start() because session_set_cookie_params rejects one, and an antedated creation date is silently ignored on Windows.
        new ReflectionProperty(Session::class, "cookieParameters")->setRawValue($session, new CookieParameters("example.test", lifetime: -3600));
        unset($session);
        $this->assertFalse(FileManager::default()->fileExists($ownURL->path));
    }

    #[Test]
    public function theDestructorSurvivesAStorageDirectoryThatIsGone(): void
    {
        $storageURL = $this->scratchStorage();
        $session = $this->sessionStoringAt($storageURL, new CookieParameters("example.test"));
        FileManager::default()->removeItem($storageURL);
        unset($session);
        $this->assertFalse(FileManager::default()->fileExists($storageURL->path));
    }

    private function scratchStorage(): URL
    {
        $storageURL = FileManager::default()->temporaryDirectory->appendingPathComponent(new UUID()->uuidString)->appendingPathComponent("Session");
        FileManager::default()->createDirectory($storageURL, true);
        return $storageURL;
    }

    private function sessionStoringAt(URL $storageURL, CookieParameters $cookieParameters): Session
    {
        $session = new Session();
        new ReflectionProperty(Session::class, "storageURL")->setRawValue($session, $storageURL);
        new ReflectionProperty(Session::class, "cookieParameters")->setRawValue($session, $cookieParameters);
        return $session;
    }
}
