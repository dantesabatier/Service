<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;

/**
 * Fetches the objects the subject may read, unserialized, for a caller that computes over them rather than returning them.
 *
 * The rows are narrowed like any other read; the fields are not filtered, because nothing here is serialized. A caller computing over a field enforces its `#[Readable]` with {@see FieldSecurityPolicy::enforceFieldRead()}.
 *
 * @internal
 * @extends PersistentSpaceOperation<ArrayClass<ManagedObject>>
 */
final class FetchPersistentSpaceOperation extends PersistentSpaceOperation
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
     * @return ArrayClass<ManagedObject>
     * @throws Exception
     */
    #[Override]
    protected function performCore(): ArrayClass
    {
        /** @var ArrayClass<ManagedObject> */
        return $this->context->fetch($this->fetchRequest);
    }
}
