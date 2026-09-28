<?php

declare(strict_types=1);

namespace Sabatier\Service\Jobs;

use Exception;
use Sabatier\CoreData\ManagedObjectContext;

/**
 * Runs a job and saves what it changed, stamping the transaction with its author.
 *
 * @internal
 */
final readonly class JobOperation
{
    public function __construct(private ManagedObjectContext $context, private Job $job, private string $transactionAuthor)
    {
    }

    /**
     * Performs the operation.
     *
     * @throws Exception
     */
    public function perform(): void
    {
        $this->context->transactionAuthor = $this->transactionAuthor;
        $this->job->run($this->context);
        if ($this->context->hasChanges) {
            $this->context->save();
        }
    }
}
