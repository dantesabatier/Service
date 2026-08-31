<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Closure;
use Generator;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\URL;
use Sabatier\Service\Endpoint;
use Sabatier\Service\EventStreamResponder;
use Sabatier\Service\EventStreamResponse;
use Sabatier\Service\HTTPCachePolicy;
use Sabatier\Service\MethodNotAllowedException;
use Sabatier\Service\NoCacheHeaderTransformer;
use Sabatier\Service\Request;
use Sabatier\Service\ResponseTransformerContext;
use Sabatier\Service\SecurityHeadersPolicy;
use Sabatier\Service\ServerSentEvent;

#[Endpoint("/notifications", transformers: [NoCacheHeaderTransformer::class])]
final class NotificationsStreamFixture extends EventStreamResponder
{
    public int $factoryReads = 0;
    public int $factoryInvocations = 0;
    public bool $contextSawFactory = false;

    #[Override]
    public Request $request {
        get => $this->request ??= new Request();
    }
    #[Override]
    protected bool $isSessionEnabled {
        get => false;
    }
    #[Override]
    protected ResponseTransformerContext $transformerContext {
        get {
            $this->contextSawFactory = $this->factoryReads === 1;
            return new ResponseTransformerContext($this->request, new HTTPCachePolicy(), securityHeadersPolicy: new SecurityHeadersPolicy(xContentTypeOptions: "nosniff"));
        }
    }
    /** @var Closure(): Generator<int, ServerSentEvent> */
    #[Override]
    protected Closure $events {
        get {
            $this->factoryReads++;
            return function (): Generator {
                $this->factoryInvocations++;
                yield new ServerSentEvent(["status" => "ready"], id: "1", event: "status");
                yield new ServerSentEvent("heartbeat", event: "ping");
            };
        }
    }
}

final class EventStreamResponderTest extends TestCase
{
    private function responder(string $method = HTTPRequestMethod::get): NotificationsStreamFixture
    {
        $responder = new NotificationsStreamFixture();
        $responder->request->url = new URL("http://localhost/notifications");
        $responder->request->httpMethod = $method;
        return $responder;
    }

    #[Test]
    public function streamsCustomEventsOnlyWhenTheResponseIsConsumed(): void
    {
        $responder = $this->responder();

        $this->assertTrue($responder->isFirstResponder);
        $response = $responder->response;

        $this->assertInstanceOf(EventStreamResponse::class, $response);
        $this->assertSame(1, $responder->factoryReads);
        $this->assertTrue($responder->contextSawFactory);
        $this->assertSame(0, $responder->factoryInvocations);
        $this->assertSame([
            "id: 1",
            "event: status",
            "data: {\"status\":\"ready\"}",
            "",
            "event: ping",
            "data: \"heartbeat\"",
            "",
        ], iterator_to_array($response));
        $this->assertSame(1, $responder->factoryInvocations);
    }

    #[Test]
    public function appliesEndpointAndInfrastructureTransformersWithoutConsumingEvents(): void
    {
        $responder = $this->responder();
        $response = $responder->response;
        $headers = $response->allHeaderFields;

        $this->assertSame("text/event-stream", $headers["Content-Type"]);
        $this->assertSame("no-store, no-cache, must-revalidate, max-age=0", $headers["Cache-Control"]);
        $this->assertSame("nosniff", $headers["X-Content-Type-Options"]);
        $this->assertSame("no", $headers["X-Accel-Buffering"]);
        $this->assertSame(0, $responder->factoryInvocations);
    }

    /** @return list<array{string}> */
    public static function unsupportedMethods(): array
    {
        return [
            [HTTPRequestMethod::head],
            [HTTPRequestMethod::options],
            [HTTPRequestMethod::post],
            [HTTPRequestMethod::put],
            [HTTPRequestMethod::patch],
            [HTTPRequestMethod::delete],
        ];
    }

    #[Test]
    #[DataProvider("unsupportedMethods")]
    public function rejectsUnsupportedMethodsBeforeReadingTheEventSource(string $method): void
    {
        $responder = $this->responder($method);
        $this->expectException(MethodNotAllowedException::class);

        try {
            $responder->response;
        } finally {
            $this->assertSame(0, $responder->factoryReads);
            $this->assertSame(0, $responder->factoryInvocations);
        }
    }
}
