<?php

declare(strict_types=1);

namespace Sabatier\Service\Jobs;

use Exception;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Date;
use function Sabatier\Foundation\class_name;

/**
 * Base class for all scheduled and one-shot jobs.
 *
 * Extend this class and place the subclass in the application's `Jobs`
 * directory to register a job with the CLI entry point. The `name` hook is
 * the key the entry point looks up against its command-line argument; it
 * defaults to the concrete class short name, so a job is run by its class
 * name unless it overrides `name`.
 *
 * A job holds business logic only. Booting the container, configuring the
 * context (`transactionAuthor`, merge policy) and persisting or resetting it
 * are the entry point's responsibility, never the job's.
 *
 * @psalm-consistent-constructor
 * @phpstan-consistent-constructor
 */
abstract class Job
{
    /** @var string Lookup key the entry point matches against the command-line argument; defaults to the concrete class short name. */
    public string $name {
        get => class_name(static::class);
    }

    /**
     * Runs the job against the already-configured context.
     *
     * Must not persist (`save`) nor reset the context: the entry point does that.
     *
     * @param ManagedObjectContext $context The context already configured with `transactionAuthor` and merge policy.
     * @throws Exception
     */
    abstract public function run(ManagedObjectContext $context): void;

    /**
     * Writes a structural progress line through `error_log` — the same channel a
     * cron redirection (`>> …log 2>&1`) captures — prefixed with the date and the
     * job's `name`. Override to send progress elsewhere.
     */
    protected function log(string $message): void
    {
        error_log(sprintf("[%s] [%s] %s", new Date()->description, $this->name, $message));
    }
}
