<?php

declare(strict_types=1);

namespace Sabatier\Service;

use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/**
 * Represents types of authorization for various actions.
 *
 * Each value corresponds to an action that can be authorized on a resource.
 */
enum AuthorizationType: int
{
    /** Permission to read a resource, typically used for GET and HEAD HTTP methods. */
    case read = 0;
    /** Permission to create a resource, typically used for POST HTTP method. */
    case create = 1;
    /** Permission to update a resource, typically used for PUT and PATCH HTTP methods. */
    case update = 2;
    /** Permission to delete a resource, typically used for DELETE HTTP method. */
    case delete = 3;
    /** Wildcard representing “any type of authorization”, used in generic queries or filters. Positive high value to avoid collision with real permissions. */
    case any = 99;

    /**
     * The action an HTTP method performs on the resource its request names.
     *
     * @param string $method The HTTP method of the request.
     * @throws MethodNotAllowedException When the method has no authorization meaning.
     */
    public static function forHTTPMethod(#[ExpectedValues(valuesFromClass: HTTPRequestMethod::class)] string $method): AuthorizationType
    {
        return match ($method) {
            HTTPRequestMethod::head, HTTPRequestMethod::get => self::read,
            HTTPRequestMethod::post => self::create,
            HTTPRequestMethod::put, HTTPRequestMethod::patch => self::update,
            HTTPRequestMethod::delete => self::delete,
            default => throw new MethodNotAllowedException()
        };
    }
}
