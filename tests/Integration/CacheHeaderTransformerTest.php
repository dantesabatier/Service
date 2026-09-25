<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\URL;
use Sabatier\Service\CacheHeaderTransformer;
use Sabatier\Service\HTTPCachePolicy;
use Sabatier\Service\Response;
use Sabatier\Service\ResponseTransformerContext;

final class CacheHeaderTransformerTest extends TestCase
{
    private function response(): Response
    {
        return new Response(new URL('http://localhost/'));
    }

    private function transform(Response $response, ?HTTPCachePolicy $policy): Response
    {
        return new CacheHeaderTransformer($response, new ResponseTransformerContext(cachePolicy: $policy))->response;
    }

    // --- Sin política ---

    #[Test]
    public function noCacheControlWhenNoPolicyInContext(): void
    {
        $result = $this->transform($this->response(), null);
        $this->assertNull($result->allHeaderFields['Cache-Control']);
    }

    // --- Cache-Control generado ---

    #[Test]
    public function cacheControlWrittenWithVisibilityAndMaxAge(): void
    {
        $policy = new HTTPCachePolicy(maxAge: 600, visibility: 'public');
        $result = $this->transform($this->response(), $policy);
        $this->assertSame('public, max-age=600', $result->allHeaderFields['Cache-Control']);
    }

    #[Test]
    public function privateVisibilityIsReflected(): void
    {
        $policy = new HTTPCachePolicy(maxAge: 300, visibility: 'private');
        $result = $this->transform($this->response(), $policy);
        $this->assertStringContainsString('private', $result->allHeaderFields['Cache-Control']);
    }

    #[Test]
    public function staleWhileRevalidateAppendedWhenConfigured(): void
    {
        $policy = new HTTPCachePolicy(maxAge: 3600, staleWhileRevalidate: 120);
        $result = $this->transform($this->response(), $policy);
        $this->assertStringContainsString('stale-while-revalidate=120', $result->allHeaderFields['Cache-Control']);
    }

    #[Test]
    public function staleWhileRevalidateAbsentWhenNull(): void
    {
        $policy = new HTTPCachePolicy(staleWhileRevalidate: null);
        $result = $this->transform($this->response(), $policy);
        $this->assertStringNotContainsString('stale-while-revalidate', $result->allHeaderFields['Cache-Control']);
    }

    #[Test]
    public function varyHeaderAddedWhenConfigured(): void
    {
        $policy = new HTTPCachePolicy(vary: 'Accept-Encoding');
        $result = $this->transform($this->response(), $policy);
        $this->assertSame('Accept-Encoding', $result->allHeaderFields['Vary']);
    }

    #[Test]
    public function varyHeaderAbsentWhenNotConfigured(): void
    {
        $policy = new HTTPCachePolicy(vary: null);
        $result = $this->transform($this->response(), $policy);
        $this->assertNull($result->allHeaderFields['Vary']);
    }

    // --- No sobreescribe Cache-Control existente ---

    #[Test]
    public function existingCacheControlIsNotOverwritten(): void
    {
        $response = $this->response();
        $response->allHeaderFields['Cache-Control'] = 'no-cache, no-store';
        $policy = new HTTPCachePolicy(maxAge: 3600, visibility: 'public');
        $result = $this->transform($response, $policy);
        $this->assertSame('no-cache, no-store', $result->allHeaderFields['Cache-Control']);
    }

    #[Test]
    public function varyNotAddedWhenCacheControlAlreadyPresent(): void
    {
        $response = $this->response();
        $response->allHeaderFields['Cache-Control'] = 'no-cache';
        $policy = new HTTPCachePolicy(vary: 'Accept');
        $result = $this->transform($response, $policy);
        $this->assertNull($result->allHeaderFields['Vary']);
    }
}
