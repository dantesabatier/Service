<?php

namespace Sabatier\Service;

/**
 * Represents the scope of an authorization.
 */
enum AuthorizationScope: int
{
    /** Access to all resources of this type */
    case all = 0;
    /** Access only to resources owned by the authenticated user */
    case own = 1;
}
