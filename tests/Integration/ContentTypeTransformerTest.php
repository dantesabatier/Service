<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\URL;
use Sabatier\Service\ContentTypeTransformer;
use Sabatier\Service\Response;
use Sabatier\Service\ResponseTransformerContext;

final class ContentTypeTransformerTest extends TestCase
{
    private function response(string $url): Response
    {
        return new Response(new URL($url));
    }

    private function transform(Response $response): Response
    {
        return (new ContentTypeTransformer($response, new ResponseTransformerContext()))->response;
    }

    // --- Detección por extensión ---

    #[Test]
    public function setsApplicationJsonForJsonExtension(): void
    {
        $result = $this->transform($this->response('http://localhost/data.json'));
        $this->assertSame('application/json', $result->allHeaderFields['Content-Type']);
    }

    #[Test]
    public function setsTextHtmlForHtmlExtension(): void
    {
        $result = $this->transform($this->response('http://localhost/page.html'));
        $this->assertSame('text/html', $result->allHeaderFields['Content-Type']);
    }

    #[Test]
    public function setsTextCssForCssExtension(): void
    {
        $result = $this->transform($this->response('http://localhost/style.css'));
        $this->assertSame('text/css', $result->allHeaderFields['Content-Type']);
    }

    #[Test]
    public function setsApplicationJavascriptForJsExtension(): void
    {
        $result = $this->transform($this->response('http://localhost/app.js'));
        $this->assertSame('application/javascript', $result->allHeaderFields['Content-Type']);
    }

    #[Test]
    public function setsImagePngForPngExtension(): void
    {
        $result = $this->transform($this->response('http://localhost/logo.png'));
        $this->assertSame('image/png', $result->allHeaderFields['Content-Type']);
    }

    #[Test]
    public function setsApplicationPdfForPdfExtension(): void
    {
        $result = $this->transform($this->response('http://localhost/document.pdf'));
        $this->assertSame('application/pdf', $result->allHeaderFields['Content-Type']);
    }

    #[Test]
    public function setsTextCsvForCsvExtension(): void
    {
        $result = $this->transform($this->response('http://localhost/export.csv'));
        $this->assertSame('text/csv', $result->allHeaderFields['Content-Type']);
    }

    // --- Sin extensión conocida ---

    #[Test]
    public function noContentTypeForUnknownExtension(): void
    {
        $result = $this->transform($this->response('http://localhost/file.xyz123'));
        $this->assertNull($result->allHeaderFields['Content-Type']);
    }

    #[Test]
    public function noContentTypeForPathWithNoExtension(): void
    {
        $result = $this->transform($this->response('http://localhost/api/users'));
        $this->assertNull($result->allHeaderFields['Content-Type']);
    }

    // --- No sobreescribe Content-Type existente ---

    #[Test]
    public function doesNotOverwriteExistingContentType(): void
    {
        $response = $this->response('http://localhost/data.json');
        $response->allHeaderFields['Content-Type'] = 'application/ld+json';
        $result = $this->transform($response);
        $this->assertSame('application/ld+json', $result->allHeaderFields['Content-Type']);
    }

    #[Test]
    public function doesNotOverwriteEvenWhenExtensionDiffers(): void
    {
        $response = $this->response('http://localhost/page.html');
        $response->allHeaderFields['Content-Type'] = 'application/json';
        $result = $this->transform($response);
        $this->assertSame('application/json', $result->allHeaderFields['Content-Type']);
    }
}
