<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectModel;

/**
 * Invalidates outstanding JWT tokens for all authorizable entities by incrementing
 * their `refreshTokenVersion`. Called when authorization entities change so that
 * `JSONWebTokenVersionEvaluator` rejects existing tokens and forces a token refresh.
 *
 * @see Authorizable::$refreshTokenVersion
 * @see JSONWebTokenVersionEvaluator
 */
final class AuthorizableTokenInvalidator
{
    private InterfaceImplementorResolver $implementorResolver {
        get => $this->implementorResolver ??= new InterfaceImplementorResolver($this->managedObjectModel);
    }
    /** @var class-string<ManagedObject> */
    private string $authorizableClass {
        get => $this->authorizableClass ??= $this->implementorResolver->resolve(Authorizable::class);
    }

    public function __construct(private readonly ManagedObjectModel $managedObjectModel)
    {
    }

    /**
     * @throws Exception
     */
    public function invalidate(ManagedObjectContext $context): void
    {
        /** @var FetchRequest<Authorizable> $fetchRequest */
        $fetchRequest = $this->authorizableClass::fetchRequest();
        foreach ($context->fetch($fetchRequest) as $user) {
            $user->refreshTokenVersion++;
        }
        $context->save();
    }
}
