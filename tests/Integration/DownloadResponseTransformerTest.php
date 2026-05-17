<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;
use Sabatier\Service\DownloadResponseTransformer;
use Sabatier\Service\Response;
use Sabatier\Service\ResponseTransformerContext;

final class DownloadResponseTransformerTest extends TestCase
{
    private function response(string $body, string $filename, string $contentType): Response
    {
        $response = new Response(new URL('http://localhost/'));
        $response->body = new Dictionary([
            'body' => $body,
            'filename' => $filename,
            'contentType' => $contentType,
        ]);
        return $response;
    }

    private function transform(Response $response): Response
    {
        return (new DownloadResponseTransformer($response, new ResponseTransformerContext()))->response;
    }

    #[Test]
    public function setsContentTypeFromData(): void
    {
        $result = $this->transform($this->response('data', 'file.csv', 'text/csv'));
        $this->assertSame('text/csv', $result->allHeaderFields['Content-Type']);
    }

    #[Test]
    public function setsContentDispositionWithFilename(): void
    {
        $result = $this->transform($this->response('data', 'report.pdf', 'application/pdf'));
        $this->assertSame('attachment; filename="report.pdf"', $result->allHeaderFields['Content-Disposition']);
    }

    #[Test]
    public function setsContentLengthFromBodySize(): void
    {
        $body = 'hello world';
        $result = $this->transform($this->response($body, 'file.txt', 'text/plain'));
        $this->assertSame((string)strlen($body), $result->allHeaderFields['Content-Length']);
    }

    #[Test]
    public function replacesBodyWithRawContent(): void
    {
        $body = 'file content here';
        $result = $this->transform($this->response($body, 'file.txt', 'text/plain'));
        $this->assertSame($body, $result->body);
    }

    #[Test]
    public function contentLengthMatchesActualBodyLength(): void
    {
        $body = str_repeat('x', 1024);
        $result = $this->transform($this->response($body, 'large.bin', 'application/octet-stream'));
        $this->assertSame('1024', $result->allHeaderFields['Content-Length']);
    }

    #[Test]
    public function filenameWithSpacesIsQuoted(): void
    {
        $result = $this->transform($this->response('data', 'my report.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
        $this->assertSame('attachment; filename="my report.xlsx"', $result->allHeaderFields['Content-Disposition']);
    }
}
