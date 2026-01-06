<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final readonly class PersistentSpaceSecurityPolicy
{
    public ?Authorizable $user;
    public bool $hasOwnScope;
    public bool $isSecurityEnabled;

    /**
     * @param Authorizable|null $user
     * @param ArrayClass<string> $scopes
     * @param bool $isSecurityEnabled
     */
    public function __construct(?Authorizable $user, ArrayClass $scopes, bool $isSecurityEnabled = true)
    {
        $this->user = $user;
        $this->hasOwnScope = $scopes->contains(fn(string $scope): bool => str_ends_with($scope, AuthorizationScope::own->name));
        $this->isSecurityEnabled = $isSecurityEnabled;
    }

    public function enforceOwnership(ManagedObject $object): void
    {
        if ($this->hasOwnScope && $this->isSecurityEnabled) {
            $service = new OwnershipService(new OwnerResolver($object), $this->user ?? fatal_error());
            $service->isOwner ?: throw new ForbiddenException();
        }
    }

    /**
     * @param ManagedObject $object
     * @param Dictionary<mixed> $body
     * @throws Exception
     */
    public function applySecureUpdate(ManagedObject $object, Dictionary $body): void
    {
        $this->securePassword($object, $body);
        $object->setValuesForKeys($this->applySecureWrite($object, $body));
    }

    /**
     * @param ManagedObject $object
     * @param Dictionary<mixed> $data
     * @return Dictionary<mixed>
     * @throws Exception
     */
    public function applySecureRead(ManagedObject $object, Dictionary $data): Dictionary
    {
        return $this->isSecurityEnabled ? new FieldSecurityFilter($object, $this->user ?? fatal_error())->filterRead($data) : $data;
    }

    /**
     * @param ManagedObject $object
     * @param Dictionary<mixed> $body
     * @return Dictionary<mixed>
     * @throws Exception
     */
    private function applySecureWrite(ManagedObject $object, Dictionary $body): Dictionary
    {
        return $this->isSecurityEnabled ? new FieldSecurityFilter($object, $this->user ?? fatal_error())->filterWrite($body) : $body;
    }

    /**
     * @param ManagedObject $object
     * @param Dictionary<mixed> $body
     */
    private function securePassword(ManagedObject $object, Dictionary $body): void
    {
        if (!$object instanceof Authorizable) {
            return;
        }
        /** @var string|null $password */
        $password = $body["password"];
        if (!$password) {
            return;
        }
        $body["password"] = password_hash($password, PASSWORD_DEFAULT);
    }
}
