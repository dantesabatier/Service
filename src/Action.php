<?php

namespace Sabatier\Service;

use Attribute;
use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/**
 * An attribute used to declare a public method of a responder as an HTTP endpoint.
 *
 * Methods annotated with this attribute are discovered at runtime by the routing
 * mechanism and are matched against incoming HTTP requests based on the declared
 * HTTP method and an optional path pattern.
 *
 * This attribute provides a lightweight and declarative way to expose operations
 * as routable actions while preserving the responder-based design of the framework.
 *
 * ### Usage
 * ```php
 * #[Action(method: HTTPRequestMethod::post, path: "/users/create")]
 * public function createUser(): Response
 * {
 *     // ...
 * }
 * ```
 *
 * If no path is provided, the routing system may infer one from the method name
 * or the enclosing responder, depending on the application's conventions.
 *
 * ### Notes
 * - Only state-changing HTTP methods are allowed (POST, PUT, PATCH, DELETE).
 * - This attribute is intended for public methods only.
 * - The attribute is immutable (`readonly`) and used exclusively as metadata
 *   for routing and request dispatch.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Action
{
    /**
     * Creates a new Action attribute describing the HTTP invocation details.
     *
     * @param string $method
     *     The HTTP method required to invoke the annotated action.
     *     Must be one of: POST, PUT, PATCH, DELETE.
     *
     * @param string|null $path
     *     An optional path pattern under which this action should be exposed.
     *     When omitted, default routing conventions are applied.
     */
    public function __construct(#[ExpectedValues([HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::patch, HTTPRequestMethod::delete])] public string $method = HTTPRequestMethod::post, public ?string $path = null)
    {
    }
}
