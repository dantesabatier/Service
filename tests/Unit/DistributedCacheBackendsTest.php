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
use Sabatier\Foundation\Set;
use Sabatier\Service\Authorizable;
use Sabatier\Service\AuthorizationScope;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\CachedAuthorization;
use Sabatier\Service\MemcachedRateLimitStore;
use Sabatier\Service\RedisAuthorizationCache;

/**
 * Drives both distributed backends against in-memory stand-ins for their client objects, so the
 * key naming, TTL handling and fallbacks are fixed without a Redis or Memcached server.
 */
final class DistributedCacheBackendsTest extends TestCase
{
    /** @throws ReflectionException */
    #[Test]
    public function anAbsentAuthorizableIsACacheMiss(): void
    {
        [$cache] = $this->redisCache();
        $this->assertNull($cache->getAuthorizableAuthorizations($this->user("ada")));
    }

    /** @throws ReflectionException */
    #[Test]
    public function authorizationsSurviveARoundTripThroughRedis(): void
    {
        [$cache] = $this->redisCache();
        $user = $this->user("ada");
        $cache->setAuthorizableAuthorizations($user, $this->authorizations("Order", "Invoice"));
        $restored = $cache->getAuthorizableAuthorizations($user);
        $this->assertSame(2, $restored?->count);
        $this->assertSame("Order", $restored[0]->name);
    }

    /** @throws ReflectionException */
    #[Test]
    public function eachAuthorizableIsCachedUnderItsOwnUsername(): void
    {
        [$cache, $redis] = $this->redisCache();
        $cache->setAuthorizableAuthorizations($this->user("ada"), $this->authorizations("Order"));
        $this->assertSame(["auth:u:ada"], array_keys($redis->entries));
        $this->assertNull($cache->getAuthorizableAuthorizations($this->user("grace")));
    }

    /** @throws ReflectionException */
    #[Test]
    public function authorizationsAreStoredWithTheConfiguredLifetime(): void
    {
        [$cache, $redis] = $this->redisCache(ttl: 120);
        $cache->setAuthorizableAuthorizations($this->user("ada"), $this->authorizations("Order"));
        $this->assertSame(120, $redis->lifetimes["auth:u:ada"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function invalidatingAnAuthorizableDropsOnlyItsOwnEntry(): void
    {
        [$cache, $redis] = $this->redisCache();
        $cache->setAuthorizableAuthorizations($this->user("ada"), $this->authorizations("Order"));
        $cache->setAuthorizableAuthorizations($this->user("grace"), $this->authorizations("Invoice"));
        $cache->invalidateAuthorizable($this->user("ada"));
        $this->assertSame(["auth:u:grace"], array_keys($redis->entries));
    }

    /** @throws ReflectionException */
    #[Test]
    public function invalidatingEverythingClearsEveryAuthorizable(): void
    {
        [$cache, $redis] = $this->redisCache();
        $cache->setAuthorizableAuthorizations($this->user("ada"), $this->authorizations("Order"));
        $cache->setAuthorizableAuthorizations($this->user("grace"), $this->authorizations("Invoice"));
        $cache->invalidateAll();
        $this->assertSame([], $redis->entries);
    }

    /** @throws ReflectionException */
    #[Test]
    public function invalidatingEverythingOnAnEmptyCacheDeletesNothing(): void
    {
        [$cache, $redis] = $this->redisCache();
        $cache->invalidateAll();
        $this->assertSame(0, $redis->deleteCalls);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aValueThatIsNotAStringIsTreatedAsAMiss(): void
    {
        [$cache, $redis] = $this->redisCache();
        $redis->entries["auth:u:ada"] = false;
        $this->assertNull($cache->getAuthorizableAuthorizations($this->user("ada")));
    }

    /** @throws ReflectionException */
    #[Test]
    public function theFirstRequestInAWindowStartsTheCounterAtOne(): void
    {
        [$store, $memcached] = $this->memcachedStore();
        $this->assertSame(1, $store->increment("ip:1.2.3.4", 60));
        // The counter is claimed with add(), which is atomic; the restart path reaches for set() and would race two workers starting the same window.
        $this->assertTrue($memcached->wasAdded("ip:1.2.3.4"));
    }

    /** @throws ReflectionException */
    #[Test]
    public function laterRequestsInTheSameWindowAdvanceTheCounter(): void
    {
        [$store] = $this->memcachedStore();
        $store->increment("ip:1.2.3.4", 60);
        $this->assertSame(2, $store->increment("ip:1.2.3.4", 60));
        $this->assertSame(3, $store->increment("ip:1.2.3.4", 60));
    }

    /** @throws ReflectionException */
    #[Test]
    public function eachClientIsCountedSeparately(): void
    {
        [$store] = $this->memcachedStore();
        $store->increment("ip:1.2.3.4", 60);
        $store->increment("ip:1.2.3.4", 60);
        $this->assertSame(1, $store->increment("ip:5.6.7.8", 60));
    }

    /** @throws ReflectionException */
    #[Test]
    public function theWindowExpiryIsRecordedAlongsideTheCounter(): void
    {
        [$store, $memcached] = $this->memcachedStore();
        $store->increment("ip:1.2.3.4", 60);
        $this->assertArrayHasKey("ip:1.2.3.4:reset", $memcached->entries);
        $this->assertGreaterThan(time() + 55, $memcached->entries["ip:1.2.3.4:reset"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aCounterThatVanishedMidWindowIsRestarted(): void
    {
        [$store, $memcached] = $this->memcachedStore();
        $memcached->refuseAdd = true;
        $memcached->refuseIncrement = true;
        $this->assertSame(1, $store->increment("ip:1.2.3.4", 60));
        $this->assertSame(1, $memcached->entries["ip:1.2.3.4"]);
        $this->assertArrayHasKey("ip:1.2.3.4:reset", $memcached->entries);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theRemainingWindowIsDerivedFromTheRecordedExpiry(): void
    {
        [$store, $memcached] = $this->memcachedStore();
        $memcached->entries["ip:1.2.3.4:reset"] = time() + 42;
        $this->assertEqualsWithDelta(42, $store->ttl("ip:1.2.3.4"), 1.0);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aClientWithoutARecordedWindowHasNoRemainingTime(): void
    {
        [$store] = $this->memcachedStore();
        $this->assertSame(0, $store->ttl("ip:1.2.3.4"));
    }

    /** @throws ReflectionException */
    #[Test]
    public function anExpiryAlreadyInThePastNeverReportsNegativeTime(): void
    {
        [$store, $memcached] = $this->memcachedStore();
        $memcached->entries["ip:1.2.3.4:reset"] = time() - 90;
        $this->assertSame(0, $store->ttl("ip:1.2.3.4"));
    }

    /**
     * @return array{RedisAuthorizationCache, Redis}
     * @throws ReflectionException
     */
    private function redisCache(int $ttl = 3600): array
    {
        $redis = new class extends Redis {
            /** @var array<string, mixed> */
            public array $entries = [];
            /** @var array<string, int> */
            public array $lifetimes = [];
            public int $deleteCalls = 0;

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
            public function del($key, ...$other_keys): Redis|int|false
            {
                $this->deleteCalls++;
                foreach (is_array($key) ? $key : [$key, ...$other_keys] as $one) {
                    unset($this->entries[$one], $this->lifetimes[$one]);
                }
                return 1;
            }

            #[Override]
            public function scan(&$iterator, $pattern = null, $count = 0, $type = null): array|false
            {
                if ($iterator === 0) {
                    return false;
                }
                $iterator = 0;
                return array_keys($this->entries) ?: false;
            }
        };
        $cache = new ReflectionClass(RedisAuthorizationCache::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(RedisAuthorizationCache::class, "redis")->setValue($cache, $redis);
        new ReflectionProperty(RedisAuthorizationCache::class, "ttl")->setValue($cache, $ttl);
        return [$cache, $redis];
    }

    /**
     * @return array{MemcachedRateLimitStore, Memcached}
     * @throws ReflectionException
     */
    private function memcachedStore(): array
    {
        $memcached = new class extends Memcached {
            /** @var array<string, mixed> */
            public array $entries = [];
            public bool $refuseAdd = false;
            public bool $refuseIncrement = false;
            /** @var list<string> */
            private array $added = [];

            public function wasAdded(string $key): bool
            {
                return in_array($key, $this->added, true);
            }

            #[Override]
            public function add($key, $value, $expiration = 0): bool
            {
                if ($this->refuseAdd || array_key_exists($key, $this->entries)) {
                    return false;
                }
                $this->entries[$key] = $value;
                $this->added[] = $key;
                return true;
            }

            #[Override]
            public function set($key, $value, $expiration = 0): bool
            {
                $this->entries[$key] = $value;
                return true;
            }

            #[Override]
            public function get($key, $cache_cb = null, $flags = 0): mixed
            {
                return $this->entries[$key] ?? false;
            }

            #[Override]
            public function increment($key, $offset = 1, $initial_value = 0, $expiry = 0): int|false
            {
                if ($this->refuseIncrement || !array_key_exists($key, $this->entries)) {
                    return false;
                }
                return $this->entries[$key] = (int)$this->entries[$key] + $offset;
            }
        };
        $store = new ReflectionClass(MemcachedRateLimitStore::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(MemcachedRateLimitStore::class, "memcached")->setValue($store, $memcached);
        return [$store, $memcached];
    }

    /** @return ArrayClass<CachedAuthorization> */
    private function authorizations(string ...$names): ArrayClass
    {
        return new ArrayClass($names)->map(fn(string $name): CachedAuthorization => new CachedAuthorization($name, AuthorizationType::read, AuthorizationScope::all));
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
