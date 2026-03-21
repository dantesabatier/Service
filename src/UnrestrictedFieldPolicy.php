<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;

/**
 * No-op policy that allows unrestricted reads and writes.
 */
final readonly class UnrestrictedFieldPolicy extends FieldSecurityPolicy
{
    #[Override]
    public function applySecureUpdate(ManagedObject $object, Dictionary $body): void
    {
        $object->updateFromSnapshot($body);
    }

    #[Override]
    public function applySecureRead(ManagedObject $object, Dictionary $data): Dictionary
    {
        return $data;
    }
}
