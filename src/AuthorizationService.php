<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CompareOptions;
use function Sabatier\Foundation\string_is_equal;

/**
 * Service interface responsible for handling authorization logic.
 */
readonly class AuthorizationService
{
    public function __construct(private AuthorizationResolver $resolver, private AuthorizationCache $inRequestCache, private ?AuthorizationCache $persistentCache = null)
    {
    }

    /**
     * Determines if the given entity is authorized to perform a specific action on a resource within the provided context.
     *
     * @param Authorizable $entity The entity requesting authorization.
     * @param string $resource The resource on which the action is to be performed.
     * @param AuthorizationType $action The type of action being requested.
     * @param ArrayClass<string> $authorizationScopes The scopes associated with the token used to authorize the request.
     * @param ManagedObjectContext $context The context in which the authorization is being evaluated.
     * @return bool Returns true if the entity is authorized, false otherwise.
     * @throws Exception
     */
    public function isAuthorized(Authorizable $entity, string $resource, AuthorizationType $action, ArrayClass $authorizationScopes, ManagedObjectContext $context): bool
    {
        $scopeToCheck = "$resource:$action->name";
        $anyScope = "$resource:any";
        if ($authorizationScopes->contains(fn(string $authorizationScope): bool => $authorizationScope === $scopeToCheck || $authorizationScope === $anyScope)) {
            return true;
        }
        if (!($authorizations = $this->inRequestCache->getAuthorizableAuthorizations($entity)) && ($authorizations = $this->persistentCache?->getAuthorizableAuthorizations($entity))) {
            $this->inRequestCache->setAuthorizableAuthorizations($entity, $authorizations);
        }
        if (!$authorizations) {
            $authorizations = $this->resolver->resolve($entity, $resource, $action, $context);
            $this->inRequestCache->setAuthorizableAuthorizations($entity, $authorizations);
            $this->persistentCache?->setAuthorizableAuthorizations($entity, $authorizations);
        }
        return $authorizations->contains(fn(Authorization $authorization): bool => string_is_equal($authorization->name, $resource, CompareOptions::caseInsensitive) && $authorization->type === $action);
    }
}
