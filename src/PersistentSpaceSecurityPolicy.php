<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;

/** @internal */
final readonly class PersistentSpaceSecurityPolicy
{
    public bool $hasOwnScope;

    /**
     * @param Authorizable $user
     * @param ArrayClass<string> $scopes
     */
    public function __construct(private Authorizable $user, ArrayClass $scopes)
    {
        $this->hasOwnScope = $scopes->contains(fn(string $scope): bool => str_ends_with($scope, AuthorizationScope::own->name));
    }

    public function enforceOwnership(ManagedObject $object): void
    {
        if ($this->hasOwnScope) {
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
        $object->setValuesForKeys(new FieldSecurityFilter($object, $this->user)->filterWrite($body));
    }

    /**
     * @param ManagedObject $object
     * @param Dictionary<mixed> $data
     * @return Dictionary<mixed>
     * @throws Exception
     */
    public function applySecureRead(ManagedObject $object, Dictionary $data): Dictionary
    {
        return new FieldSecurityFilter($object, $this->user)->filterRead($data);
    }
}
