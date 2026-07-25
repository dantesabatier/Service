<?php

declare(strict_types=1);

namespace Sabatier\Service\Jobs;

use RuntimeException;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Service\Application;
use Throwable;
use function Sabatier\Foundation\human_readable_time;
use function Sabatier\Foundation\human_readable_value;

/**
 * The command-line run loop, counterpart to `Application::run()`.
 *
 * `Application::run()` is the HTTP entry point: it boots the framework and
 * answers a request. `JobRunner::run()` is the CLI entry point: it boots the
 * same framework by hand — the delegate hooks, the Core Data context, the
 * transaction author — resolves the job named on the command line against the
 * discovered `Job` classes, runs it, and persists. A project's `cli.php` is
 * then as thin as its `index.php`:
 *
 * ```php
 * Application::shared()->…  // index.php → run()
 * new JobRunner()->run();   // cli.php
 * ```
 *
 * The loop owns the transaction boundary (`save` on success, `reset` always)
 * and the structural log lines (`started`, `completed in …`, `failed: …`),
 * written with the same `[date] [name]` prefix a job's own `Job::log` uses, so
 * both streams read uniformly. It never returns: like `Application::run()`, it
 * ends the process — `exit(0)` on success, `exit(1)` on failure or misuse.
 */
final class JobRunner
{
    private JobRegistry $registry {
        get => $this->registry ??= new JobRegistry(new JobResolver()->resolve());
    }

    /**
     * The transaction author stamped on every write the job performs, so
     * persistent history records who ran it. Defaults to `"system"`.
     */
    public function __construct(private readonly string $transactionAuthor = "system")
    {
    }

    /**
     * Boots the framework, runs the job named by the first command-line
     * argument, and ends the process. Never returns.
     *
     * @return never
     */
    public function run(): never
    {
        $name = ProcessInfo::processInfo()->arguments[1] ?? null;
        if ($name === null) {
            fwrite(STDERR, sprintf("Usage: php cli.php <Job>%sAvailable jobs: %s%s", PHP_EOL, $this->registry->names->join(", "), PHP_EOL));
            exit(1);
        }
        $job = $this->registry->job($name);
        if ($job === null) {
            fwrite(STDERR, sprintf("Unknown job \"%s\". Available: %s%s", $name, $this->registry->names->join(", "), PHP_EOL));
            exit(1);
        }

        $time = ProcessInfo::processInfo()->systemUptime;
        $application = Application::shared();
        $delegate = $application->delegate ?? throw new RuntimeException("The application has no delegate.");
        // The delegate is an ObjectClass (the generated delegate extends it); its
        // class-level `initialize()` is declared there, not on the delegate interface —
        // the same cast Application makes when it boots the delegate over HTTP.
        /** @var class-string<ObjectClass> $delegateClass */
        $delegateClass = $delegate::class;
        $delegateClass::initialize();
        $delegate->applicationWillFinishLaunching($application);
        $context = $application->persistentContainer->viewContext;
        $context->transactionAuthor = $this->transactionAuthor;

        $this->log($name, "started");
        $status = 0;
        try {
            $job->run($context);
            if ($context->hasChanges) {
                $context->save();
            }
            $duration = ProcessInfo::processInfo()->systemUptime - $time |> human_readable_time(...);
            $this->log($name, sprintf("completed in %s", $duration));
        } catch (Throwable $throwable) {
            $this->log($name, sprintf("failed: %s", $throwable |> human_readable_value(...)));
            $status = 1;
        } finally {
            $context->reset();
        }
        exit($status);
    }

    /**
     * Writes a structural line through `error_log` — the channel a cron
     * redirection (`>> …log 2>&1`) captures — with the same `[date] [name]`
     * prefix `Job::log` uses for a job's own progress.
     */
    private function log(string $name, string $message): void
    {
        error_log(sprintf("[%s] [%s] %s", new Date()->description, $name, $message));
    }
}
