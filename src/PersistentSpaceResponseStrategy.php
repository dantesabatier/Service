<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;

/** @internal */
abstract class PersistentSpaceResponseStrategy extends ResponseStrategy
{
    protected readonly EntityDescription $entity;
    protected readonly ManagedObjectContext $managedObjectContext;
    protected readonly AuthorizationContext $authorizationContext;
    private bool $hasOwnScope {
        get => $this->hasOwnScope ??= $this->authorizationContext->scopes->contains(fn(string $scope): bool => str_ends_with($scope, AuthorizationScope::own->name));
    }

    public function __construct(Request $request, EntityDescription $entity, ManagedObjectContext $managedObjectContext, AuthorizationContext $authorizationContext)
    {
        parent::__construct($request);
        $this->entity = $entity;
        $this->managedObjectContext = $managedObjectContext;
        $this->authorizationContext = $authorizationContext;
    }

    protected function fetchBy(ManagedObjectID|int $objectID): ?ManagedObject
    {
        /** @var FetchRequest<ManagedObject> $fetchRequest */
        $fetchRequest = new FetchRequest();
        $fetchRequest->entity = $this->entity;
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath(ServiceIdentity::identityKey), Expression::expressionForConstantValue($objectID));
        if ($serialization = $this->request->serialization) {
            $fetchRequest->serialization = $serialization;
        }
        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->managedObjectContext->fetch($fetchRequest)->first;
    }

    protected function enforceOwnership(ManagedObject $object): void
    {
        if ($this->hasOwnScope) {
            $service = new OwnershipService(new OwnerResolver($object), $this->authorizationContext->user);
            $service->isOwner ?: throw new ForbiddenException();
        }
    }

    /**
     * @param ManagedObject $object
     * @param Dictionary<mixed> $body
     */
    protected function applySecureUpdate(ManagedObject $object, Dictionary $body): void
    {
        $object->setValuesForKeys(new FieldSecurityFilter($object, $this->authorizationContext->user)->filterWrite($body));
    }

    /**
     * @param ManagedObject $object
     * @param Dictionary<mixed> $data
     * @return Dictionary<mixed>
     */
    protected function applySecureRead(ManagedObject $object, Dictionary $data): Dictionary
    {
        return new FieldSecurityFilter($object, $this->authorizationContext->user)->filterRead($data);
    }

    /**
     * @throws Exception
     */
    protected function executeSecureFetch(FetchRequest $fetchRequest): ArrayClass|Dictionary
    {
        $context = $this->managedObjectContext;
        $fetchRequestResult = match ($fetchRequest->resultType) {
            FetchRequestResultType::managedObjectResultType,
            FetchRequestResultType::managedObjectIDResultType,
            FetchRequestResultType::dictionaryResultType => $context->fetch($fetchRequest),
            FetchRequestResultType::countResultType => new Dictionary(["count" => $context->count($fetchRequest)])
        };
        if ($fetchRequest->resultType === FetchRequestResultType::managedObjectResultType) {
            $fetchRequestResult = $fetchRequestResult->map(fn(ManagedObject $object): Dictionary => new FieldSecurityFilter($object, $this->authorizationContext->user)->filterRead($object->jsonSerialize()));
        }
        return $fetchRequestResult;
    }
}
