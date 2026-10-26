<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;

/**
 * Fetches the rows the subject may read, each object filtered to the fields the subject may read as it is accessed.
 *
 * @internal
 * @extends FetchPersistentSpaceOperation<ArrayClass<mixed>>
 */
final class ReadPersistentSpaceOperation extends FetchPersistentSpaceOperation
{
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
