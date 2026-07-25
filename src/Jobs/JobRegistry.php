<?php

declare(strict_types=1);

namespace Sabatier\Service\Jobs;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;

/**
 * Holds the resolved jobs and looks them up by name.
 *
 * The lookup key is each job's `name` hook, never the class name: the entry
 * point matches a command-line argument against `job()` and runs only what it
 * finds. Exposes `$names` for the "available jobs" usage message.
 */
final class JobRegistry
{
    /** @var Dictionary<Job> */
    private Dictionary $jobs {
        get => $this->jobs ??= $this->jobList->reduce(new Dictionary(),
            /**
             * @param Dictionary<Job> $carry
             * @return Dictionary<Job>
             */
            function (Dictionary $carry, Job $job) {
                $carry[$job->name] = $job;
                return $carry;
            });
    }
    /** @var ArrayClass<string> The names of every registered job, for the usage message. */
    public ArrayClass $names {
        get => $this->jobs->keys;
    }

    /** @param ArrayClass<Job> $jobList */
    public function __construct(private readonly ArrayClass $jobList)
    {
    }

    /** Returns the job registered under `$name`, or `null` when none matches. */
    public function job(string $name): ?Job
    {
        return $this->jobs[$name];
    }
}
