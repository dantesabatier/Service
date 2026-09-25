<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Sabatier\Service\Response;
use Sabatier\Service\ResponsePipeline;
use Sabatier\Service\ResponseTransformer;
use Sabatier\Service\ResponseTransformerContext;

// Transformers auxiliares para los tests

final class AppendHeaderTransformer extends ResponseTransformer
{
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        $existing = (string)($response->allHeaderFields['X-Trace'] ?? '');
        $response->allHeaderFields['X-Trace'] = $existing === '' ? 'A' : $existing . '-A';
        parent::__construct($response, $context);
    }
}

final class AppendBTransformer extends ResponseTransformer
{
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        $existing = (string)($response->allHeaderFields['X-Trace'] ?? '');
        $response->allHeaderFields['X-Trace'] = $existing === '' ? 'B' : $existing . '-B';
        parent::__construct($response, $context);
    }
}

final class SetBodyTransformer extends ResponseTransformer
{
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        $response->body = 'transformed';
        parent::__construct($response, $context);
    }
}

final class ResponsePipelineTest extends TestCase
{
    private function response(): Response
    {
        return new Response(new URL('http://localhost/'));
    }

    #[Test]
    public function emptyPipelineReturnsResponseUnchanged(): void
    {
        $response = $this->response();
        $result = new ResponsePipeline(new Set([]))->process($response);
        $this->assertSame($response, $result);
    }

    #[Test]
    public function singleTransformerIsApplied(): void
    {
        $result = new ResponsePipeline(new Set([SetBodyTransformer::class]))->process($this->response());
        $this->assertSame('transformed', $result->body);
    }

    #[Test]
    public function transformersExecuteInDeclarationOrder(): void
    {
        $result = new ResponsePipeline(new Set([AppendHeaderTransformer::class, AppendBTransformer::class]))->process($this->response());
        $this->assertSame('A-B', $result->allHeaderFields['X-Trace']);
    }

    #[Test]
    public function reversedOrderProducesDifferentResult(): void
    {
        $result = new ResponsePipeline(new Set([AppendBTransformer::class, AppendHeaderTransformer::class]))->process($this->response());
        $this->assertSame('B-A', $result->allHeaderFields['X-Trace']);
    }

    #[Test]
    public function eachTransformerReceivesOutputOfPrevious(): void
    {
        $result = new ResponsePipeline(new Set([SetBodyTransformer::class, AppendHeaderTransformer::class]))->process($this->response());
        $this->assertSame('transformed', $result->body);
        $this->assertSame('A', $result->allHeaderFields['X-Trace']);
    }

    #[Test]
    public function contextIsPassedToEveryTransformer(): void
    {
        $context = new ResponseTransformerContext();
        $result = new ResponsePipeline(new Set([AppendHeaderTransformer::class, AppendBTransformer::class]), $context)->process($this->response());
        $this->assertSame('A-B', $result->allHeaderFields['X-Trace']);
    }
}
