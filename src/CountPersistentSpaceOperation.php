<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObjectContext;

/**
 * Counts the rows the subject may read.
 *
 * @internal
 * @extends PersistentSpaceOperation<int>
 */
final class CountPersistentSpaceOperation extends PersistentSpaceOperation
{
    public function __construct(ManagedObjectContext $context, FieldSecurityPolicy $fieldSecurityPolicy, private readonly FetchRequest $fetchRequest)
    {
        parent::__construct($context, $fieldSecurityPolicy);
    }

    #[Override]
    protected function willPerform(): void
    {
        $this->fieldSecurityPolicy->applyReadScope($this->fetchRequest);
    }

    #[Override]
    protected function performCore(): int
    {
        return $this->context->count($this->fetchRequest);
    }
}
