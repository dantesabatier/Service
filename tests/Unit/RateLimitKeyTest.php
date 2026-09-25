<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\InMemoryRateLimitStore;

/**
 * Fixes which counters a request is charged against.
 *
 * The username reaching the limiter is the one the request claims, not one that has been verified —
 * the limiter runs first on purpose, since verifying costs a Core Data lookup and a bcrypt comparison
 * that an unauthenticated caller must not be able to spend at will. That makes the shape of the key
 * the whole defence, and these cases are that shape. `Application::enforceRateLimitIfNeeded()` reads
 * superglobals and a store through the singleton, so the keying is mirrored here rather than driven
 * through the responder.
 *
 * @see \Sabatier\Service\Application
 */
final class RateLimitKeyTest extends TestCase
{
    /**
     * The keys and limits one request is charged against, mirrored from `Application::enforceRateLimitIfNeeded()`.
     *
     * @return Dictionary<int>
     * @noinspection PhpSameParameterValueInspection
     */
    private function limits(string $address, ?string $username, int $maximumForIP, int $maximumForUser): Dictionary
    {
        /** @var Dictionary<int> $limits */
        $limits = new Dictionary(["rate_limit:ip:$address" => $username !== null ? $maximumForUser : $maximumForIP]);
        if ($username !== null) {
            $limits["rate_limit:ip:$address:user:$username"] = $maximumForUser;
        }
        return $limits;
    }

    /** An anonymous request is charged to its address alone, at the anonymous allowance. */
    #[Test]
    public function anAnonymousRequestIsChargedToItsAddress(): void
    {
        $limits = $this->limits("1.2.3.4", null, 30, 120);

        $this->assertSame(["rate_limit:ip:1.2.3.4"], array_keys($limits->array));
        $this->assertSame(30, $limits["rate_limit:ip:1.2.3.4"]);
    }

    /** A request claiming a subject is charged twice: to its address, and to the pair of address and subject. */
    #[Test]
    public function aClaimedSubjectIsChargedToBothCounters(): void
    {
        $limits = $this->limits("1.2.3.4", "alice", 30, 120);

        $this->assertSame(["rate_limit:ip:1.2.3.4", "rate_limit:ip:1.2.3.4:user:alice"], array_keys($limits->array));
        $this->assertSame(120, $limits["rate_limit:ip:1.2.3.4"], "A client that names itself is not held to the anonymous allowance.");
        $this->assertSame(120, $limits["rate_limit:ip:1.2.3.4:user:alice"]);
    }

    /**
     * Claiming someone else's username cannot spend their quota.
     *
     * Every key carries the address, which is the one part of the request a caller cannot assert, so an
     * attacker asserting a victim's name fills a counter of their own rather than the victim's.
     */
    #[Test]
    public function claimingAnotherSubjectDoesNotTouchTheirCounter(): void
    {
        $victim = $this->limits("10.0.0.1", "alice", 30, 120);
        $attacker = $this->limits("192.0.2.9", "alice", 30, 120);

        $this->assertNotSame(array_keys($victim->array), array_keys($attacker->array));
        $this->assertSame([], array_intersect(array_keys($victim->array), array_keys($attacker->array)), "The two requests must share no counter.");
    }

    /**
     * Inventing a fresh username per request buys no extra allowance.
     *
     * The per-subject counter is new every time, but the address counter is the same one, so the
     * address allowance still bounds the whole run — which is what keeps the limiter from being
     * bypassed by a caller who simply keeps renaming itself.
     */
    #[Test]
    public function rotatingUsernamesStillShareTheAddressCounter(): void
    {
        $store = new InMemoryRateLimitStore();
        $counts = [];
        foreach (["a", "b", "c", "d", "e"] as $username) {
            foreach (array_keys($this->limits("1.2.3.4", $username, 30, 120)->array) as $key) {
                $counts[(string)$key] = $store->increment((string)$key, 60);
            }
        }

        $this->assertSame(5, $counts["rate_limit:ip:1.2.3.4"], "All five requests landed on the same address counter.");
        foreach (["a", "b", "c", "d", "e"] as $username) {
            $this->assertSame(1, $counts["rate_limit:ip:1.2.3.4:user:$username"], "Each invented name got its own fresh counter, which is why the address one has to exist.");
        }
    }
}
