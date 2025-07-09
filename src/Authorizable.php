<?php

namespace Sabatier\Service;

/**
 * Represents an entity that is both authenticatable and can have authorization capabilities.
 */
interface Authorizable extends Authenticatable
{
    /**
     * Provides the authorization for a given request.
     *
     * @param Request $request The incoming request that requires authorization.
     * @return Authorization|null Returns an Authorization object for the given request if Authorizable has one.
     */
    public function authorization(Request $request): ?Authorization;
}
