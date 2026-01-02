<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;

/** @internal */
final readonly class PersistentSpaceSecurityPolicy
{
    private bool $hasOwnScope;

    public function __construct(protected AuthorizationContext $authorizationContext)
    {
        $this->hasOwnScope = $this->authorizationContext->scopes->contains(fn(string $scope): bool => str_ends_with($scope, AuthorizationScope::own->name));
    }

    public function enforceOwnership(ManagedObject $object): void
    {
        if ($this->hasOwnScope) {
            $service = new OwnershipService(new OwnerResolver($object), $this->authorizationContext->user);
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
        $object->setValuesForKeys(new FieldSecurityFilter($object, $this->authorizationContext->user)->filterWrite($body));
    }

    /**
     * @param ManagedObject $object
     * @param Dictionary<mixed> $data
     * @return Dictionary<mixed>
     * @throws Exception
     */
    public function applySecureRead(ManagedObject $object, Dictionary $data): Dictionary
    {
        return new FieldSecurityFilter($object, $this->authorizationContext->user)->filterRead($data);
    }
}
