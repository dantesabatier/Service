<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Foundation\Number;
use Sabatier\Foundation\ProcessInfo;
use function Sabatier\Foundation\base64_url_encode;
use function Sabatier\Foundation\read_random;
use const Sabatier\Service\MCPSessionTTLDefault;
use const Sabatier\Service\MCPSessionTTLKey;

/**
 * Derives the storage key of an MCP session and issues new session identifiers.
 *
 * The key combines the subject that owns the session with the identifier the server issued,
 * so a guessed identifier resolves only under the identity that owns it and never reaches
 * another user's session. The subject is supplied by the caller, which reads it from the
 * verified token rather than from anything the client writes.
 *
 * @internal
 */
final class MCPSessionKey
{
    private const string prefix = "mcp.session";
    private const int identifierBytes = 32;

    /** @var int The idle lifetime of a session, in seconds. */
    public int $timeToLive {
        get => new Number((string)(ProcessInfo::processInfo()->environment[MCPSessionTTLKey] ?: MCPSessionTTLDefault))->intValue;
    }

    /**
     * Returns a new, cryptographically secure session identifier.
     *
     * Encoded without padding or non-alphanumeric characters, so the value stays inside the
     * visible ASCII range the specification requires of a session identifier.
     */
    public function identifier(): string
    {
        return self::identifierBytes
                |> read_random(...)
                |> base64_url_encode(...);
    }

    /**
     * Returns the storage key of a session belonging to the given subject.
     *
     * @param string $subject The username of the identity that owns the session.
     * @param string $identifier The session identifier the client presented or the server issued.
     */
    public function key(string $subject, string $identifier): string
    {
        return sprintf("%s.%s.%s", self::prefix, $subject, $identifier);
    }
}
