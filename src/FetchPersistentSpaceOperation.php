<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObjectContext;

/**
 * The scope is folded into the request here rather than in each subclass, so no subclass can read a row the subject may not see; what a subclass decides is only how the result of that one request is consumed.
 *
 * Performed as it is, it answers the objects themselves, narrowed to the rows the subject may see, for a caller that computes over them; their fields are not filtered, because nothing here is serialized.
 *
 * @internal
 * @template TResult
 * @extends PersistentSpaceOperation<TResult>
 */
class FetchPersistentSpaceOperation extends PersistentSpaceOperation
{
    /**
     * @param ManagedObjectContext $context
     * @param FieldSecurityPolicy $fieldSecurityPolicy
     * @param FetchRequest $fetchRequest The request every subclass reads through, narrowed to the subject's read scope before it runs.
     */
    public function __construct(ManagedObjectContext $context, FieldSecurityPolicy $fieldSecurityPolicy, protected readonly FetchRequest $fetchRequest)
    {
        parent::__construct($context, $fieldSecurityPolicy);
    }

    #[Override]
    protected function willPerform(): void
    {
        $this->fieldSecurityPolicy->applyReadScope($this->fetchRequest);
    }

    /**
     * @return TResult
     * @throws Exception
     */
    #[Override]
    protected function performCore(): mixed
    {
        /** @var TResult */
        return $this->context->fetch($this->fetchRequest);
    }
}
