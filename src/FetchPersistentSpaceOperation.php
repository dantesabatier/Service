<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObjectContext;

/**
 * The scope is folded into the request here rather than in each subclass, so no subclass can read a row the subject may not see; what a subclass decides is only how the result of that one request is consumed.
 *
 * @internal
 * @template TResult
 * @extends PersistentSpaceOperation<TResult>
 */
abstract class FetchPersistentSpaceOperation extends PersistentSpaceOperation
{
    public function __construct(ManagedObjectContext $context, FieldSecurityPolicy $fieldSecurityPolicy, protected readonly FetchRequest $fetchRequest)
    {
        parent::__construct($context, $fieldSecurityPolicy);
    }

    #[Override]
    protected function willPerform(): void
    {
        $this->fieldSecurityPolicy->applyReadScope($this->fetchRequest);
    }
}
