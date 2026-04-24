<?php

namespace Sabatier\Service;

/**
 * Base class for all response transformers.
 *
 * A ResponseTransformer represents a single step in the response transformation
 * pipeline. It receives a Response instance and applies a mutation to it,
 * allowing incremental modification of the response as it flows through
 * the pipeline.
 *
 * Transformers are typically declared in Action or Endpoint attributes,
 * where they are composed into an ordered sequence and executed after
 * the action logic completes.
 *
 * Behavior:
 * - Each transformer operates on the same Response instance.
 * - Transformations are applied sequentially, in declaration order.
 * - Execution order is significant and may affect the final output.
 *
 * Usage:
 * - Extend this class to implement custom response transformations.
 * - Perform all mutations within the constructor.
 * - Common use cases include serialization (e.g. JSON), header injection,
 *   caching policies, and security headers.
 *
 * Design considerations:
 * - The Response is intentionally mutable to allow efficient in-place updates.
 * - Transformers should focus on a single responsibility.
 * - Transformers should not perform unrelated side effects outside the Response.
 *
 * Example:
 *
 * <code>
 * final class JSONTransformer extends ResponseTransformer
 * {
 *     public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
 *     {
 *         $response->body = json_encode($response->body);
 *         $response->allHeaderFields["Content-Type"] = "application/json";
 *         parent::__construct($response, $context);
 *     }
 * }
 * </code>
 *
 * @psalm-consistent-constructor
 * @phpstan-consistent-constructor
 */
abstract class ResponseTransformer
{
    public function __construct(public readonly Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
    }
}
