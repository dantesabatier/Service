<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\fatal_error;

/**
 * Default restrictive policy that applies field-level security filters.
 */
final readonly class SecureObjectPropertyPolicy extends ManagedObjectSecurityPolicy
{
    #[Override]
    public function applySecureUpdate(ManagedObject $object, Dictionary $body): void
    {
        $this->securePassword($object, $body);
        $object->updateFromSnapshot($this->applySecureWrite($object, $body));
    }

    #[Override]
    public function applySecureRead(ManagedObject $object, Dictionary $data): Dictionary
    {
        return $this->isSecurityEnabled ? new FieldSecurityFilter($object, $this->user ?? fatal_error())->filterRead($data) : $data;
    }

    /**
     * @throws Exception
     */
    private function applySecureWrite(ManagedObject $object, Dictionary $body): Dictionary
    {
        return $this->isSecurityEnabled ? new FieldSecurityFilter($object, $this->user ?? fatal_error())->filterWrite($body) : $body;
    }

    private function securePassword(ManagedObject $object, Dictionary $body): void
    {
        if ($object instanceof Authorizable) {
            /** @var string|null $password */
            $password = $body["password"];
            if ($password) {
                $body["password"] = password_hash($password, PASSWORD_DEFAULT);
            }
        }
    }
}
