<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;

/** @internal */
final readonly class PersistentSpaceSecurityPolicy
{
    private Authorizable $user;
    public bool $hasOwnScope;
    public bool $isSecurityEnabled;

    /**
     * @param Authorizable $user
     * @param ArrayClass<string> $scopes
     * @param bool $isSecurityEnabled
     */
    public function __construct(Authorizable $user, ArrayClass $scopes, bool $isSecurityEnabled = true)
    {
        $this->user = $user;
        $this->hasOwnScope = $scopes->contains(fn(string $scope): bool => str_ends_with($scope, AuthorizationScope::own->name));
        $this->isSecurityEnabled = $isSecurityEnabled;
    }

    public function enforceOwnership(ManagedObject $object): void
    {
        if ($this->hasOwnScope && $this->isSecurityEnabled) {
            $service = new OwnershipService(new OwnerResolver($object), $this->user);
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
        if ($this->isSecurityEnabled) {
            $object->setValuesForKeys(new FieldSecurityFilter($object, $this->user)->filterWrite($body));
        }
    }

    /**
     * @param ManagedObject $object
     * @param Dictionary<mixed> $data
     * @return Dictionary<mixed>
     * @throws Exception
     */
    public function applySecureRead(ManagedObject $object, Dictionary $data): Dictionary
    {
        return $this->isSecurityEnabled ? new FieldSecurityFilter($object, $this->user)->filterRead($data) : $data;
    }
}
