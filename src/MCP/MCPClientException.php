<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use RuntimeException;
use Sabatier\Foundation\Error;
use Throwable;

/** Describes a transport or protocol failure that prevented an MCP client from getting a usable response. */
final class MCPClientException extends RuntimeException
{
    /**
     * @param string $message What prevented the MCP request from completing.
     * @param bool $isTransient Whether establishing a new session or trying later may succeed.
     * @param Throwable|null $previous The lower-level transport or decoding failure, when one exists.
     * @param Error|null $transportError The Foundation transport error, before it is normalized into the message.
     */
    public function __construct(string $message, public readonly bool $isTransient, ?Throwable $previous = null, public readonly ?Error $transportError = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
