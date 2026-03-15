<?php

namespace Sabatier\Service;

/**
 * Represents a base decorator for a Response object.
 * This class is designed to be extended to modify or enhance
 * the behavior of a Response instance dynamically.
 *
 * The decorator pattern allows for wrapping a Response
 * object to add additional functionality without altering the
 * original object's structure.
 *
 * The Response instance to be decorated is passed to the constructor
 * and stored as a property for use in subclasses.
 */
abstract class ResponseDecorator
{
    public function __construct(public readonly Response $response)
    {
    }
}
