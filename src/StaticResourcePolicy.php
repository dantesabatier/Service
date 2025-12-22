<?php

namespace Sabatier\Service;

use Sabatier\Foundation\URL;

/**
 * Defines the policy used to determine how the application
 * should handle static resources.
 *
 * Implementations may decide whether a given resource:
 * - should be handled as a static resource
 * - is optional (e.g., favicon)
 * - allows empty responses when missing
 * - can be cached by clients and intermediaries
 *
 * This interface is intended to be implemented by framework users
 * to customize static resource handling behavior.
 */
interface StaticResourcePolicy
{
    /**
     * Evaluates the given resource URL and returns the disposition describing how it should be handled.
     * @param URL $resourceURL The resource URL to evaluate.
     * @return StaticResourceDisposition The disposition describing how the resource should be handled.
     */
    public function evaluate(URL $resourceURL): StaticResourceDisposition;
}
