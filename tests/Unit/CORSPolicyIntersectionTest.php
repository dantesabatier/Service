<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Set;
use Sabatier\Service\CORSPolicy;

/**
 * Tests the intersection logic that Responder applies when scoping the global CORSPolicy
 * down to a per-responder policy. The intersection is expressed as:
 *
 *   new CORSPolicy(
 *       global->allowedOrigins,
 *       global->allowedMethods->intersection(responder->allowedMethods),
 *       global->allowedHeaders->intersection(responder->allowedHeaders),
 *       global->allowCredentials,
 *       global->exposedHeaders,
 *   )
 */
final class CORSPolicyIntersectionTest extends TestCase
{
    private function intersect(CORSPolicy $global, ArrayClass $responderMethods, ArrayClass $responderHeaders): CORSPolicy
    {
        return new CORSPolicy(
            $global->allowedOrigins,
            $global->allowedMethods->intersection(new Set($responderMethods)),
            $global->allowedHeaders->intersection(new Set($responderHeaders)),
            $global->allowCredentials,
            $global->exposedHeaders,
        );
    }

    #[Test]
    public function originsPassThroughUnchanged(): void
    {
        $global = new CORSPolicy(allowedOrigins: new Set(['https://example.com', '*']));
        $result = $this->intersect($global, new ArrayClass(['GET']), new ArrayClass([]));
        $this->assertTrue($result->allowsOrigin('https://example.com'));
        $this->assertTrue($result->allowsOrigin('*'));
    }

    #[Test]
    public function methodsReducedToIntersection(): void
    {
        $global = new CORSPolicy(allowedMethods: new Set(['GET', 'POST', 'DELETE']));
        $result = $this->intersect($global, new ArrayClass(['GET', 'POST']), new ArrayClass([]));
        $this->assertTrue($result->allowedMethods->contains(fn(string $m) => $m === 'GET'));
        $this->assertTrue($result->allowedMethods->contains(fn(string $m) => $m === 'POST'));
        $this->assertFalse($result->allowedMethods->contains(fn(string $m) => $m === 'DELETE'));
    }

    #[Test]
    public function headersReducedToIntersection(): void
    {
        $global = new CORSPolicy(allowedHeaders: new Set(['Authorization', 'Content-Type', 'X-Custom']));
        $result = $this->intersect($global, new ArrayClass(), new ArrayClass(['Authorization', 'Content-Type']));
        $this->assertTrue($result->allowedHeaders->contains(fn(string $h) => $h === 'Authorization'));
        $this->assertTrue($result->allowedHeaders->contains(fn(string $h) => $h === 'Content-Type'));
        $this->assertFalse($result->allowedHeaders->contains(fn(string $h) => $h === 'X-Custom'));
    }

    #[Test]
    public function emptyIntersectionProducesEmptyMethods(): void
    {
        $global = new CORSPolicy(allowedMethods: new Set(['DELETE']));
        $result = $this->intersect($global, new ArrayClass(['GET']), new ArrayClass([]));
        $this->assertTrue($result->allowedMethods->isEmpty);
    }

    #[Test]
    public function credentialsPassThroughUnchanged(): void
    {
        $global = new CORSPolicy(allowCredentials: true);
        $result = $this->intersect($global, new ArrayClass(), new ArrayClass([]));
        $this->assertTrue($result->allowCredentials);
    }

    #[Test]
    public function exposedHeadersPassThroughUnchanged(): void
    {
        $global = new CORSPolicy(exposedHeaders: new Set(['X-Request-Id']));
        $result = $this->intersect($global, new ArrayClass(), new ArrayClass([]));
        $this->assertTrue($result->exposedHeaders->contains(fn(string $h) => $h === 'X-Request-Id'));
    }

    #[Test]
    public function responderWithNoMethodsProducesEmptyIntersection(): void
    {
        $global = new CORSPolicy(allowedMethods: new Set(['GET', 'POST']));
        $result = $this->intersect($global, new ArrayClass(), new ArrayClass([]));
        $this->assertTrue($result->allowedMethods->isEmpty);
    }

    #[Test]
    public function globalWithNoMethodsAlwaysProducesEmptyIntersection(): void
    {
        $global = new CORSPolicy();
        $result = $this->intersect($global, new ArrayClass(['GET', 'POST', 'DELETE']), new ArrayClass([]));
        $this->assertTrue($result->allowedMethods->isEmpty);
    }
}
