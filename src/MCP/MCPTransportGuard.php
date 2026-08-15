<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\Set;
use Sabatier\Service\Application;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\ForbiddenException;
use Sabatier\Service\MCPSession;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\Request;
use function Sabatier\Foundation\localized_string;
use function Sabatier\Foundation\string_split_trimmed;
use const Sabatier\Service\MCPAllowedOriginsKey;
use const Sabatier\Service\MCPProtocolVersionHeader;
use const Sabatier\Service\MCPSessionHeader;

/**
 * Enforces the Streamable HTTP transport requirements before a message is dispatched.
 *
 * The transport rules are about *where* a request comes from and *whether the handshake
 * happened*, never about who the caller is: authentication stays with the access evaluator
 * chain, which runs on every request regardless of what this guard concludes.
 *
 * Three checks, each rejecting with the status the specification prescribes:
 *
 * - **Origin** — a request without an `Origin` header passes. MCP clients are not browsers
 *   and send none; only a page running in the user's browser does, and that is the DNS
 *   rebinding vector the check exists to close. Inverting this would lock out every real
 *   client while admitting the one caller it is meant to reject.
 * - **Session** — a method other than the handshake must carry the session the server
 *   issued. Missing is `400`; unknown or expired is `404`, which tells the client to
 *   initialize again rather than to give up.
 * - **Protocol version** — an unsupported version is `400`. An absent header means the
 *   legacy version, as the specification requires for backwards compatibility.
 *
 * @see MCPSession
 * @see \Sabatier\Service\MCPSessionStore
 * @internal
 */
final readonly class MCPTransportGuard
{
    /** @var Set<string> The protocol versions this server implements. */
    private Set $supportedProtocolVersions;

    /**
     * @param string $subject The username of the authenticated identity, taken from the verified token. Sessions are scoped to it, so a session established by one identity is invisible to another.
     * @param MCPSessionKey $sessionKey Derives storage keys and issues identifiers.
     * @param Set<string>|null $supportedProtocolVersions The versions to accept, or null for the built-in set.
     */
    public function __construct(private string $subject = "", private MCPSessionKey $sessionKey = new MCPSessionKey(), ?Set $supportedProtocolVersions = null)
    {
        $this->supportedProtocolVersions = $supportedProtocolVersions ?? MCPProtocolVersion::supported();
    }

    /**
     * Rejects the request unless it satisfies every transport requirement.
     *
     * @param Request $request The incoming request.
     * @param string|null $method The JSON-RPC method being invoked, or null when the body carries none.
     * @throws ForbiddenException When the request declares an origin the server does not allow.
     * @throws BadRequestException When the session identifier is absent, or the protocol version is unsupported.
     * @throws NotFoundException When the session identifier is unknown or has expired.
     */
    public function enforce(Request $request, ?string $method): void
    {
        $this->enforceOrigin($request);
        $this->enforceProtocolVersion($request);
        $this->enforceSession($request, $method);
    }

    /**
     * Returns the session the request carries, refreshing its expiry, or null when it carries none.
     *
     * Called after `enforce()`, so a session identifier that reaches this point is known to
     * resolve. Rewriting the entry on every request is what keeps a session in active use
     * from expiring underneath its client.
     *
     * @param Request $request The incoming request.
     */
    public function touchSession(Request $request): ?MCPSession
    {
        if (!($identifier = $this->sessionIdentifier($request))) {
            return null;
        }
        $key = $this->sessionKey->key($this->subject, $identifier);
        if (!($session = Application::shared()->mcpSessionStore->get($key))) {
            return null;
        }
        Application::shared()->mcpSessionStore->store($key, $session, $this->sessionKey->timeToLive);
        return $session;
    }

    /**
     * Ends the session the request carries, if any.
     *
     * @param Request $request The incoming request.
     */
    public function endSession(Request $request): void
    {
        if (!($identifier = $this->sessionIdentifier($request))) {
            return;
        }
        Application::shared()->mcpSessionStore->delete($this->sessionKey->key($this->subject, $identifier));
    }

    /**
     * @throws ForbiddenException
     */
    private function enforceOrigin(Request $request): void
    {
        if (!($origin = $request->valueForHttpHeaderField("Origin"))) {
            return;
        }
        $allowed = new Set(string_split_trimmed((string)(ProcessInfo::processInfo()->environment[MCPAllowedOriginsKey] ?? "")));
        if ($allowed->contains(fn(string $allowedOrigin): bool => $allowedOrigin === "*" || $allowedOrigin === $origin)) {
            return;
        }
        throw new ForbiddenException(localized_string("The origin of this request is not allowed to reach the MCP endpoint."));
    }

    /**
     * @throws BadRequestException
     */
    private function enforceProtocolVersion(Request $request): void
    {
        if (!($version = $request->valueForHttpHeaderField(MCPProtocolVersionHeader))) {
            return;
        }
        if ($this->supportedProtocolVersions->containsElement($version)) {
            return;
        }
        throw new BadRequestException(sprintf(localized_string("Unsupported MCP protocol version: %s."), $version));
    }

    /**
     * @throws BadRequestException
     * @throws NotFoundException
     */
    private function enforceSession(Request $request, ?string $method): void
    {
        $identifier = $this->sessionIdentifier($request);
        if ($identifier === null) {
            if (MCPMethod::isAllowedWithoutSession($method)) {
                return;
            }
            throw new BadRequestException(localized_string("This request must carry the session identifier issued during initialization."));
        }
        if (Application::shared()->mcpSessionStore->get($this->sessionKey->key($this->subject, $identifier)) !== null) {
            return;
        }
        throw new NotFoundException(localized_string("This MCP session has expired. Initialize again to establish a new one."));
    }

    private function sessionIdentifier(Request $request): ?string
    {
        return $request->valueForHttpHeaderField(MCPSessionHeader) ?: null;
    }
}
