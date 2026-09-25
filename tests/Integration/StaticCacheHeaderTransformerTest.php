<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\URL;
use Sabatier\Service\Response;
use Sabatier\Service\ResponseTransformerContext;
use Sabatier\Service\StaticCacheHeaderTransformer;
use Sabatier\Service\StaticResourceDisposition;

final class StaticCacheHeaderTransformerTest extends TestCase
{
    private function response(): Response
    {
        return new Response(new URL("http://localhost/app.js"));
    }

    private function transform(Response $response, ?StaticResourceDisposition $disposition): Response
    {
        return new StaticCacheHeaderTransformer($response, new ResponseTransformerContext(staticResourceDisposition: $disposition))->response;
    }

    private function cacheable(int $maxAge, bool $immutable): StaticResourceDisposition
    {
        return new StaticResourceDisposition(true, false, false, true, true, $maxAge, $immutable);
    }

    // --- Recursos cacheables ---

    #[Test]
    public function writesPublicMaxAgeAndImmutableForBundleResource(): void
    {
        $result = $this->transform($this->response(), $this->cacheable(31536000, true));
        $this->assertSame("public, max-age=31536000, immutable", $result->allHeaderFields["Cache-Control"]);
    }

    #[Test]
    public function omitsImmutableWhenNotFlagged(): void
    {
        $result = $this->transform($this->response(), $this->cacheable(86400, false));
        $this->assertSame("public, max-age=86400", $result->allHeaderFields["Cache-Control"]);
    }

    #[Test]
    public function writesZeroMaxAgeWhenDispositionSaysSo(): void
    {
        $result = $this->transform($this->response(), $this->cacheable(0, false));
        $this->assertSame("public, max-age=0", $result->allHeaderFields["Cache-Control"]);
    }

    // --- Sin disposición o no cacheable ---

    #[Test]
    public function leavesResponseUntouchedWhenDispositionAbsent(): void
    {
        $result = $this->transform($this->response(), null);
        $this->assertNull($result->allHeaderFields["Cache-Control"]);
    }

    #[Test]
    public function leavesResponseUntouchedWhenNotCacheable(): void
    {
        $disposition = new StaticResourceDisposition(true, false, false, false, false);
        $result = $this->transform($this->response(), $disposition);
        $this->assertNull($result->allHeaderFields["Cache-Control"]);
    }

    // --- No sobreescribe Cache-Control existente ---

    #[Test]
    public function overwritesAnyPreexistingCacheControl(): void
    {
        $response = $this->response();
        $response->allHeaderFields["Cache-Control"] = "no-store";
        $result = $this->transform($response, $this->cacheable(600, false));
        $this->assertSame("public, max-age=600", $result->allHeaderFields["Cache-Control"]);
    }
}
