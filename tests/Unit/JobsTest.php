<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\UUID;
use Sabatier\Service\Jobs\Job;
use Sabatier\Service\Jobs\JobRegistry;
use Sabatier\Service\Jobs\JobResolver;
use Sabatier\Service\Jobs\JobRunner;

class RecordingJobFixture extends Job
{
    public int $runCount = 0;

    #[Override]
    public function run(ManagedObjectContext $context): void
    {
        $this->runCount++;
    }

    public function logThrough(string $message): void
    {
        $this->log($message);
    }
}

final class RenamedJobFixture extends RecordingJobFixture
{
    #[Override]
    public string $name {
        get => "nightly-rollup";
    }
}

final class SecondJobFixture extends RecordingJobFixture
{
}

final class JobsTest extends TestCase
{
    #[Test]
    public function aJobIsNamedAfterItsClassByDefault(): void
    {
        $this->assertSame("RecordingJobFixture", new RecordingJobFixture()->name);
    }

    #[Test]
    public function aJobMayDecoupleItsNameFromItsClass(): void
    {
        $this->assertSame("nightly-rollup", new RenamedJobFixture()->name);
    }

    #[Test]
    public function aJobRunsAgainstTheContextItIsHanded(): void
    {
        $job = new RecordingJobFixture();
        $job->run($this->context());
        $this->assertSame(1, $job->runCount);
    }

    #[Test]
    public function aJobLogsWithItsDateAndNamePrefix(): void
    {
        $this->assertMatchesRegularExpression("/^\\[\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}] \\[RecordingJobFixture] rebuilt 12 rows$/", $this->captureErrorLog(fn() => new RecordingJobFixture()->logThrough("rebuilt 12 rows")));
    }

    #[Test]
    public function aRenamedJobLogsUnderTheNameItChose(): void
    {
        $this->assertStringContainsString("[nightly-rollup]", $this->captureErrorLog(fn() => new RenamedJobFixture()->logThrough("done")));
    }

    #[Test]
    public function theRegistryLooksAJobUpByItsName(): void
    {
        $job = new RecordingJobFixture();
        $this->assertSame($job, new JobRegistry(new ArrayClass([$job]))->job("RecordingJobFixture"));
    }

    #[Test]
    public function theRegistryKeysARenamedJobUnderItsChosenName(): void
    {
        $registry = new JobRegistry(new ArrayClass([new RenamedJobFixture()]));
        $this->assertNotNull($registry->job("nightly-rollup"));
        $this->assertNull($registry->job("RenamedJobFixture"));
    }

    #[Test]
    public function anUnknownNameResolvesToNothing(): void
    {
        $this->assertNull(new JobRegistry(new ArrayClass([new RecordingJobFixture()]))->job("Nope"));
    }

    #[Test]
    public function anEmptyRegistryResolvesNothingAndNamesNothing(): void
    {
        $registry = new JobRegistry(new ArrayClass());
        $this->assertNull($registry->job("RecordingJobFixture"));
        $this->assertTrue($registry->names->isEmpty);
    }

    #[Test]
    public function theRegistryNamesEveryJobItHolds(): void
    {
        $names = new JobRegistry(new ArrayClass([new RecordingJobFixture(), new RenamedJobFixture(), new SecondJobFixture()]))->names->array;
        sort($names);
        $this->assertSame(["RecordingJobFixture", "SecondJobFixture", "nightly-rollup"], $names);
    }

    #[Test]
    public function theLastJobRegisteredUnderAConflictingNameWins(): void
    {
        $second = new RecordingJobFixture();
        $registry = new JobRegistry(new ArrayClass([new RecordingJobFixture(), $second]));
        $this->assertSame($second, $registry->job("RecordingJobFixture"));
        $this->assertSame(1, $registry->names->count);
    }

    #[Test]
    public function theRegistryIsBuiltOnceAndReused(): void
    {
        $registry = new JobRegistry(new ArrayClass([new RecordingJobFixture()]));
        $jobs = new ReflectionProperty(JobRegistry::class, "jobs");
        $this->assertSame($jobs->getValue($registry), $jobs->getValue($registry));
    }

    #[Test]
    public function aBundleWithoutAJobsDirectoryResolvesNoJobs(): void
    {
        $this->assertTrue(new JobResolver()->resolve()->isEmpty);
    }

    #[Test]
    public function theRunnerBuildsItsRegistryFromTheResolver(): void
    {
        $this->assertTrue($this->registryOf(new JobRunner())->names->isEmpty);
    }

    #[Test]
    public function theRunnersRegistryIsBuiltOnce(): void
    {
        $runner = new JobRunner();
        $this->assertSame($this->registryOf($runner), $this->registryOf($runner));
    }

    #[Test]
    public function theRunnerLogsWithTheSameDateAndNamePrefixAJobUses(): void
    {
        $this->assertMatchesRegularExpression("/^\\[\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}] \\[Rollup] started$/", $this->captureErrorLog(fn() => new ReflectionMethod(JobRunner::class, "log")->invoke(new JobRunner(), "Rollup", "started")));
    }

    private function registryOf(JobRunner $runner): JobRegistry
    {
        /** @var JobRegistry */
        return new ReflectionProperty(JobRunner::class, "registry")->getValue($runner);
    }

    private function context(): ManagedObjectContext
    {
        return new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
    }

    /**
     * error_log writes to the SAPI logger, so the destination is redirected to a file for the call
     * and restored afterwards; there is no way to observe the default channel from inside the process.
     */
    private function captureErrorLog(callable $body): string
    {
        $destination = ini_get("error_log");
        $url = FileManager::default()->temporaryDirectory->appendingPathComponent(new UUID()->uuidString);
        ini_set("error_log", $url->path);
        try {
            $body();
        } finally {
            ini_set("error_log", $destination === false ? "" : $destination);
        }
        $contents = (string)FileManager::default()->contents($url->path);
        FileManager::default()->removeItem($url);
        // Writing to a file makes error_log prepend its own "[06-Oct-2026 21:33:19 UTC] " stamp,
        // which is the logger's and not the line the framework composed. The zone is whatever the
        // machine runs in — a region name, an abbreviation or an offset — so the stamp is matched
        // by its fixed date shape rather than by what follows the time.
        return (string)preg_replace("/^\\[\\d{2}-[A-Za-z]{3}-\\d{4} \\d{2}:\\d{2}:\\d{2} [^]]+] /", "", trim($contents));
    }
}
