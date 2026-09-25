<?php

/** @noinspection PhpPossiblePolymorphicInvocationInspection */

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Memcached;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Redis;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Set;
use Sabatier\Service\Authorizable;
use Sabatier\Service\AuthorizationScope;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\CachedAuthorization;
use Sabatier\Service\IdempotentResponse;
use Sabatier\Service\InMemoryIdempotencyStore;
use Sabatier\Service\MCPSession;
use Sabatier\Service\MemcachedAuthorizationCache;
use Sabatier\Service\MemcachedIdempotencyStore;
use Sabatier\Service\RedisIdempotencyStore;
use Sabatier\Service\RedisMCPSessionStore;
use Sabatier\Service\RedisRateLimitStore;

/**
 * Covers the remaining distributed and in-process stores against stand-ins for their clients, so
 * the key naming, TTL handling and miss behaviour are fixed without a Redis or Memcached server.
 */
final class RemainingStoreBackendsTest extends TestCase
{
    protected function tearDown(): void
    {
        InMemoryIdempotencyStore::reset();
        parent::tearDown();
    }

    /** @throws ReflectionException */
    #[Test]
    public function anUnknownKeyIsAnIdempotencyMissInRedis(): void
    {
        [$store] = $this->redisIdempotency();
        $this->assertNull($store->get("idem:1"));
    }

    /** @throws ReflectionException */
    #[Test]
    public function anIdempotentResponseSurvivesARoundTripThroughRedis(): void
    {
        [$store] = $this->redisIdempotency();
        $store->store("idem:1", new IdempotentResponse(HTTPStatusCode::created, new Dictionary(["X-Trace" => "abc"]), "payload"), 60);
        $restored = $store->get("idem:1");
        $this->assertSame(HTTPStatusCode::created, $restored?->statusCode);
        $this->assertSame("payload", $restored->body);
        $this->assertSame("abc", $restored->headers["X-Trace"]);
        $this->assertFalse($restored->isProcessing);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anIdempotentResponseIsStoredWithTheLifetimeItWasGiven(): void
    {
        [$store, $redis] = $this->redisIdempotency();
        $store->store("idem:1", new IdempotentResponse(HTTPStatusCode::ok, new Dictionary(), null), 90);
        $this->assertSame(90, $redis->lifetimes["idem:1"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anInFlightMarkerSurvivesTheRoundTrip(): void
    {
        [$store] = $this->redisIdempotency();
        $store->store("idem:1", new IdempotentResponse(HTTPStatusCode::accepted, new Dictionary(), null, true), 60);
        $this->assertTrue($store->get("idem:1")?->isProcessing);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aValueThatIsNotAStringIsAnIdempotencyMiss(): void
    {
        [$store, $redis] = $this->redisIdempotency();
        $redis->entries["idem:1"] = 17;
        $this->assertNull($store->get("idem:1"));
    }

    /** @throws ReflectionException */
    #[Test]
    public function theFirstRequestInARedisWindowStartsAtOneAndSetsTheExpiry(): void
    {
        [$store, $redis] = $this->redisRateLimit();
        $this->assertSame(1, $store->increment("ip:1.2.3.4", 60));
        $this->assertSame(60, $redis->lifetimes["ip:1.2.3.4"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function laterRequestsAdvanceTheCounterWithoutResettingTheWindow(): void
    {
        [$store, $redis] = $this->redisRateLimit();
        $store->increment("ip:1.2.3.4", 60);
        $redis->lifetimes = [];
        $this->assertSame(2, $store->increment("ip:1.2.3.4", 60));
        $this->assertSame([], $redis->lifetimes);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theRemainingRedisWindowIsReportedAsItStands(): void
    {
        [$store, $redis] = $this->redisRateLimit();
        $redis->remainingTTL = 42;
        $this->assertSame(42, $store->ttl("ip:1.2.3.4"));
    }

    /** @throws ReflectionException */
    #[Test]
    public function aKeyWithoutAWindowHasNoRemainingRedisTime(): void
    {
        [$store, $redis] = $this->redisRateLimit();
        $redis->remainingTTL = -2;
        $this->assertSame(0, $store->ttl("ip:1.2.3.4"));
    }

    /** @throws ReflectionException */
    #[Test]
    public function anUnknownKeyIsASessionMiss(): void
    {
        [$store] = $this->redisSessions();
        $this->assertNull($store->get("mcp:s1"));
    }

    /** @throws ReflectionException */
    #[Test]
    public function anMCPSessionSurvivesARoundTrip(): void
    {
        [$store] = $this->redisSessions();
        $store->store("mcp:s1", new MCPSession("ada", "2025-11-25", "probe", "1.0"), 300);
        $restored = $store->get("mcp:s1");
        $this->assertSame("ada", $restored?->subject);
        $this->assertSame("2025-11-25", $restored->protocolVersion);
        $this->assertSame("probe", $restored->clientName);
        $this->assertSame("1.0", $restored->clientVersion);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anMCPSessionIsStoredWithItsLifetime(): void
    {
        [$store, $redis] = $this->redisSessions();
        $store->store("mcp:s1", new MCPSession("ada", "2025-11-25"), 300);
        $this->assertSame(300, $redis->lifetimes["mcp:s1"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aDeletedSessionIsGone(): void
    {
        [$store] = $this->redisSessions();
        $store->store("mcp:s1", new MCPSession("ada", "2025-11-25"), 300);
        $store->delete("mcp:s1");
        $this->assertNull($store->get("mcp:s1"));
    }

    /** @throws ReflectionException */
    #[Test]
    public function aSessionValueThatIsNotAStringIsAMiss(): void
    {
        [$store, $redis] = $this->redisSessions();
        $redis->entries["mcp:s1"] = false;
        $this->assertNull($store->get("mcp:s1"));
    }

    /** @throws ReflectionException */
    #[Test]
    public function authorizationsSurviveARoundTripThroughMemcached(): void
    {
        [$cache] = $this->memcachedAuthorizations();
        $user = $this->user("ada");
        $cache->setAuthorizableAuthorizations($user, new ArrayClass([new CachedAuthorization("Order", AuthorizationType::read, AuthorizationScope::all)]));
        $this->assertSame("Order", $cache->getAuthorizableAuthorizations($user)?->first?->name);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anUncachedAuthorizableIsAMemcachedMiss(): void
    {
        [$cache] = $this->memcachedAuthorizations();
        $this->assertNull($cache->getAuthorizableAuthorizations($this->user("ada")));
    }

    /** @throws ReflectionException */
    #[Test]
    public function invalidatingAnAuthorizableDropsOnlyItsMemcachedEntry(): void
    {
        [$cache] = $this->memcachedAuthorizations();
        $cache->setAuthorizableAuthorizations($this->user("ada"), new ArrayClass());
        $cache->setAuthorizableAuthorizations($this->user("grace"), new ArrayClass());
        $cache->invalidateAuthorizable($this->user("ada"));
        $this->assertNull($cache->getAuthorizableAuthorizations($this->user("ada")));
        $this->assertNotNull($cache->getAuthorizableAuthorizations($this->user("grace")));
    }

    /** @throws ReflectionException */
    #[Test]
    public function anIdempotentResponseSurvivesARoundTripThroughMemcached(): void
    {
        [$store] = $this->memcachedIdempotency();
        $store->store("idem:1", new IdempotentResponse(HTTPStatusCode::created, new Dictionary(), "payload"), 60);
        $this->assertSame("payload", $store->get("idem:1")?->body);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anUnknownKeyIsAnIdempotencyMissInMemcached(): void
    {
        [$store] = $this->memcachedIdempotency();
        $this->assertNull($store->get("idem:1"));
    }

    #[Test]
    public function anIdempotentResponseSurvivesARoundTripInMemory(): void
    {
        $store = new InMemoryIdempotencyStore();
        $store->store("idem:1", new IdempotentResponse(HTTPStatusCode::created, new Dictionary(), "payload"), 60);
        $this->assertSame("payload", $store->get("idem:1")?->body);
    }

    #[Test]
    public function anUnknownKeyIsAnIdempotencyMissInMemory(): void
    {
        $this->assertNull(new InMemoryIdempotencyStore()->get("idem:absent"));
    }

    #[Test]
    public function anEntryPastItsLifetimeIsAMissAndIsDroppedInMemory(): void
    {
        $store = new InMemoryIdempotencyStore();
        $store->store("idem:1", new IdempotentResponse(HTTPStatusCode::ok, new Dictionary(), "stale"), -1);
        $this->assertNull($store->get("idem:1"));
        /** @var array<string, mixed> $entries */
        $entries = new ReflectionProperty(InMemoryIdempotencyStore::class, "entries")->getValue();
        $this->assertArrayNotHasKey("idem:1", $entries);
    }

    #[Test]
    public function theInMemoryStoreIsSharedAcrossInstances(): void
    {
        new InMemoryIdempotencyStore()->store("idem:1", new IdempotentResponse(HTTPStatusCode::ok, new Dictionary(), "shared"), 60);
        $this->assertSame("shared", new InMemoryIdempotencyStore()->get("idem:1")?->body);
    }

    #[Test]
    public function resettingClearsEveryInMemoryEntry(): void
    {
        $store = new InMemoryIdempotencyStore();
        $store->store("idem:1", new IdempotentResponse(HTTPStatusCode::ok, new Dictionary(), "x"), 60);
        InMemoryIdempotencyStore::reset();
        $this->assertNull($store->get("idem:1"));
    }

    /**
     * @return array{RedisIdempotencyStore, Redis}
     * @throws ReflectionException
     */
    private function redisIdempotency(): array
    {
        $redis = $this->redis();
        return [$this->withRedis(RedisIdempotencyStore::class, $redis), $redis];
    }

    /**
     * @return array{RedisRateLimitStore, Redis}
     * @throws ReflectionException
     */
    private function redisRateLimit(): array
    {
        $redis = $this->redis();
        return [$this->withRedis(RedisRateLimitStore::class, $redis), $redis];
    }

    /**
     * @return array{RedisMCPSessionStore, Redis}
     * @throws ReflectionException
     */
    private function redisSessions(): array
    {
        $redis = $this->redis();
        return [$this->withRedis(RedisMCPSessionStore::class, $redis), $redis];
    }

    /**
     * @param class-string $storeClass
     * @throws ReflectionException
     */
    private function withRedis(string $storeClass, Redis $redis): object
    {
        $store = new ReflectionClass($storeClass)->newInstanceWithoutConstructor();
        new ReflectionProperty($storeClass, "redis")->setValue($store, $redis);
        return $store;
    }

    /**
     * @return array{MemcachedAuthorizationCache, Memcached}
     * @throws ReflectionException
     */
    private function memcachedAuthorizations(): array
    {
        $memcached = $this->memcached();
        $cache = new ReflectionClass(MemcachedAuthorizationCache::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(MemcachedAuthorizationCache::class, "memcached")->setValue($cache, $memcached);
        new ReflectionProperty(MemcachedAuthorizationCache::class, "ttl")->setValue($cache, 3600);
        return [$cache, $memcached];
    }

    /**
     * @return array{MemcachedIdempotencyStore, Memcached}
     * @throws ReflectionException
     */
    private function memcachedIdempotency(): array
    {
        $memcached = $this->memcached();
        $store = new ReflectionClass(MemcachedIdempotencyStore::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(MemcachedIdempotencyStore::class, "memcached")->setValue($store, $memcached);
        return [$store, $memcached];
    }

    private function redis(): Redis
    {
        return new class extends Redis {
            /** @var array<string, mixed> */
            public array $entries = [];
            /** @var array<string, int> */
            public array $lifetimes = [];
            public int $remainingTTL = 0;

            #[Override]
            public function get($key): mixed
            {
                return $this->entries[$key] ?? false;
            }

            #[Override]
            public function setex($key, $expire, $value): Redis|bool
            {
                $this->entries[$key] = $value;
                $this->lifetimes[$key] = $expire;
                return true;
            }

            #[Override]
            public function incr($key, $by = 1): Redis|int|false
            {
                return $this->entries[$key] = (int)($this->entries[$key] ?? 0) + $by;
            }

            #[Override]
            public function expire($key, $timeout, $mode = null): Redis|bool
            {
                $this->lifetimes[$key] = $timeout;
                return true;
            }

            #[Override]
            public function ttl($key): Redis|int|false
            {
                return $this->remainingTTL;
            }

            #[Override]
            public function del($key, ...$other_keys): Redis|int|false
            {
                unset($this->entries[$key]);
                return 1;
            }
        };
    }

    private function memcached(): Memcached
    {
        return new class extends Memcached {
            /** @var array<string, mixed> */
            public array $entries = [];

            #[Override]
            public function get($key, $cache_cb = null, $flags = 0): mixed
            {
                return $this->entries[$key] ?? false;
            }

            #[Override]
            public function set($key, $value, $expiration = 0): bool
            {
                $this->entries[$key] = $value;
                return true;
            }

            #[Override]
            public function delete($key, $time = 0): bool
            {
                unset($this->entries[$key]);
                return true;
            }
        };
    }

    private function user(string $username): Authorizable
    {
        return new class ($username) implements Authorizable {
            public function __construct(private readonly string $name)
            {
            }

            public string $username { get => $this->name; }
            public ?string $password { get => null; }
            public bool $isEnabled { get => true; }
            public int $refreshTokenVersion { get => 1; set {} }
            public Set $roles { get => new Set(); }

            #[Override]
            public function isEqual(mixed $other): bool
            {
                return $this === $other;
            }

            #[Override]
            public static function defaultRepresentation(): Dictionary
            {
                return new Dictionary();
            }
        };
    }
}
