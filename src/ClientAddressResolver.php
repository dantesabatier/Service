<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\Set;

/**
 * Resolves the client IP address for an incoming request.
 *
 * By default the client address is `REMOTE_ADDR` — the peer that actually opened the TCP
 * connection, which a client cannot forge. When the application runs behind a reverse proxy or
 * load balancer, the real client address arrives in `X-Forwarded-For`, but that header is
 * attacker-controlled unless the immediate peer is a proxy the application trusts. Trust is
 * therefore explicit: `X-Forwarded-For` is consulted only when `REMOTE_ADDR` is a declared trusted
 * proxy, walking the forwarded chain right-to-left and skipping further trusted hops so the first
 * untrusted address is taken as the client.
 *
 * Configure trusted proxies via `Application::$trustedProxies` in the delegate. With none declared
 * (the default), the resolver always returns `REMOTE_ADDR` and ignores `X-Forwarded-For`.
 *
 * @see Application::$trustedProxies
 */
final readonly class ClientAddressResolver
{
    /** @var Set<string> Trusted proxy IP addresses whose `X-Forwarded-For` may be believed. */
    private Set $trustedProxies;

    /**
     * @param Set<string> $trustedProxies Trusted proxy IP addresses. Empty disables `X-Forwarded-For` handling.
     */
    public function __construct(Set $trustedProxies = new Set())
    {
        $this->trustedProxies = $trustedProxies;
    }

    /**
     * Resolves the client IP for the given request, or null when it cannot be determined.
     */
    public function resolve(Request $request): ?string
    {
        /** @var string|null $remoteAddress */
        $remoteAddress = $_SERVER["REMOTE_ADDR"] ?? null;
        if ($remoteAddress === null || $this->trustedProxies->isEmpty || !$this->trustedProxies->containsElement($remoteAddress)) {
            return $remoteAddress;
        }
        $forwarded = $request->valueForHttpHeaderField("X-Forwarded-For");
        if (!$forwarded) {
            return $remoteAddress;
        }
        foreach (array_reverse(explode(",", $forwarded)) as $hop) {
            $address = trim($hop);
            if ($address === "") {
                continue;
            }
            if (!$this->trustedProxies->containsElement($address)) {
                return $address;
            }
        }
        return $remoteAddress;
    }
}
