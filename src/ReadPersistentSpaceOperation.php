<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;

/**
 * Fetches the rows the subject may read, each object filtered to the fields the subject may read as it is accessed.
 *
 * @internal
 * @extends PersistentSpaceOperation<ArrayClass<mixed>>
 */
final class ReadPersistentSpaceOperation extends PersistentSpaceOperation
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

    /**
     * @return ArrayClass<mixed>
     * @throws Exception
     */
    #[Override]
    protected function performCore(): ArrayClass
    {
        return new SecureReadArray($this->context->fetch($this->fetchRequest), $this->fieldSecurityPolicy);
    }
}
