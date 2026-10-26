<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;

/**
 * Counts the rows the subject may read.
 *
 * @internal
 * @extends FetchPersistentSpaceOperation<int>
 */
final class CountPersistentSpaceOperation extends FetchPersistentSpaceOperation
{
    #[Override]
    protected function performCore(): int
    {
        return $this->context->count($this->fetchRequest);
    }
}
