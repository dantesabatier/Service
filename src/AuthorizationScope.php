<?php

namespace Sabatier\Service;

/**
 * Defines the data scope covered by an `Authorization`.
 *
 * - `all` — the permission applies to every record of the target resource type,
 *   regardless of ownership. Use for administrators or service accounts.
 * - `own` — the permission applies only to records where the authenticated user
 *   is the owner. `OwnershipService` enforces this at the query and mutation level.
 *
 * @see Authorization
 * @see AuthorizationService
 */
enum AuthorizationScope: int
{
    /** Access to all resources of this type */
    case all = 0;
    /** Access only to resources owned by the authenticated user */
    case own = 1;
}
