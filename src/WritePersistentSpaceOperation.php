<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;

/**
 * The order is the guarantee, which is why {@see self::performCore()} is final: a subclass says which row it writes, which action that row is authorized for and what the change is, never when the rules run against it.
 *
 * @internal
 * @template TResult
 * @extends PersistentSpaceOperation<TResult>
 */
abstract class WritePersistentSpaceOperation extends PersistentSpaceOperation
{
    /** @var AuthorizationType The action the row is authorized for before the change is applied. */
    abstract protected AuthorizationType $authorizationType {
        get;
    }
    /** @var ManagedObject The row this operation writes, read once so the rules and the answer see the same object. */
    abstract protected ManagedObject $subject {
        get;
    }
    /** @var TResult The stored row as the subject may read it. */
    protected mixed $answer {
        /** @throws Exception */
        get {
            /** @var TResult */
            return $this->storedRepresentation($this->subject, $this->serialization);
        }
    }

    /**
     * @param ManagedObjectContext $context
     * @param FieldSecurityPolicy $fieldSecurityPolicy
     * @param EntityDescription $entity
     * @param Dictionary<mixed>|null $serialization The shape the stored row is read back in, or `null` for the whole row.
     */
    public function __construct(ManagedObjectContext $context, FieldSecurityPolicy $fieldSecurityPolicy, protected readonly EntityDescription $entity, protected readonly ?Dictionary $serialization = null)
    {
        parent::__construct($context, $fieldSecurityPolicy);
    }

    /**
     * @return TResult
     * @throws Exception
     */
    #[Override]
    final protected function performCore(): mixed
    {
        $object = $this->subject;
        $this->fieldSecurityPolicy->enforceOwnership($object, $this->authorizationType);
        $this->fieldSecurityPolicy->enforceResourceAccess($object);
        $this->mutate($object);
        if ($this->context->hasChanges) {
            $this->context->save();
        }
        return $this->answer;
    }

    /**
     * @throws Exception
     */
    abstract protected function mutate(ManagedObject $object): void;
}
