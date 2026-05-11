<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CompareOptions;
use function Sabatier\Foundation\string_is_equal;

/**
 * Evaluates whether an authenticated entity is permitted to perform an action on a resource.
 *
 * Authorization is checked in three layers, from fastest to slowest:
 * 1. **Token scopes** — if the JWT or session token already carries an explicit scope string
 *    matching `{resource}:{action}` or `{resource}:any`, access is granted immediately without
 *    any cache or database lookup.
 * 2. **In-request cache** — a per-request `AuthorizationCache` (always present) stores the
 *    resolved authorization set for the current request's lifetime.
 * 3. **Persistent cache** — an optional cross-request `AuthorizationCache` (e.g. Redis or APCu)
 *    reduces database round-trips for repeat requests by the same user.
 *
 * If neither cache has a result, `AuthorizationResolver` fetches the entity's roles and
 * authorizations from the database and populates both caches for subsequent lookups.
 *
 * `AuthorizationService` is exposed on `Application` and `Responder` as a shared service.
 * Override `Application::$authorizationCache` or `Application::$authorizationService` in the
 * application delegate to customize cache backends or authorization logic.
 *
 * @see AuthorizationCache
 * @see AuthorizationResolver
 * @see Application::$authorizationService
 */
final readonly class AuthorizationService
{
    public function __construct(private AuthorizationResolver $resolver, private AuthorizationCache $inRequestCache, private ?AuthorizationCache $persistentCache = null)
    {
    }

    /**
     * Invalidates the cached authorizations for a given authorizable entity across all cache layers.
     *
     * @param Authorizable $authorizable The authorizable entity whose cached authorizations are to be invalidated.
     */
    public function invalidateAuthorizable(Authorizable $authorizable): void
    {
        $this->inRequestCache->invalidateAuthorizable($authorizable);
        $this->persistentCache?->invalidateAuthorizable($authorizable);
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
        $anyAction = AuthorizationType::any;
        $scopeToCheck = "$resource:$action->name";
        $anyScope = "$resource:$anyAction->name";
        if ($authorizationScopes->contains(fn(string $authorizationScope): bool => str_starts_with($authorizationScope, $scopeToCheck) || str_starts_with($authorizationScope, $anyScope))) {
            return true;
        }
        if (!($authorizations = $this->inRequestCache->getAuthorizableAuthorizations($entity)) && ($authorizations = $this->persistentCache?->getAuthorizableAuthorizations($entity))) {
            $this->inRequestCache->setAuthorizableAuthorizations($entity, $authorizations);
        }
        if (!$authorizations) {
            $authorizations = $this->resolver->resolve($entity, $context)->map(fn(Authorization $authorization): CachedAuthorization => new CachedAuthorization($authorization->name, $authorization->type, $authorization->scope));
            $this->inRequestCache->setAuthorizableAuthorizations($entity, $authorizations);
            $this->persistentCache?->setAuthorizableAuthorizations($entity, $authorizations);
        }
        return $authorizations->contains(fn(Authorization $authorization): bool => string_is_equal($authorization->name, $resource, CompareOptions::caseInsensitive) && (($authorization->type === $action) || ($authorization->type === $anyAction)));
    }
}
