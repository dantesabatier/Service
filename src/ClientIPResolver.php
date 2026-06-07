<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use function Sabatier\Foundation\string_split_trimmed;

/** @internal */
final readonly class ClientIPResolver
{
    /** @param string[] $trustedProxies */
    public function __construct(private array $trustedProxies = [])
    {
    }

    public function resolve(): string
    {
        $remoteAddr = $_SERVER["REMOTE_ADDR"] ?? "unknown";
        if (!$this->trustedProxies || !isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            return $remoteAddr;
        }
        $proxies = new ArrayClass($this->trustedProxies);
        $chain = new ArrayClass(string_split_trimmed($_SERVER["HTTP_X_FORWARDED_FOR"]));
        $chain->append($remoteAddr);
        return $chain->last(fn(string $ip): bool => !$proxies->contains(fn(string $proxy): bool => str_contains($proxy, "/") ? $this->ipInCIDR($ip, $proxy) : $ip === $proxy)) ?? $remoteAddr;
    }

    private function ipInCIDR(string $ip, string $cidr): bool
    {
        $parts = explode("/", $cidr, 2);
        if (!isset($parts[1])) {
            return false;
        }
        [$subnet, $bits] = $parts;
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        $mask = $bits === "0" ? 0 : (~0 << (32 - (int)$bits));
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
