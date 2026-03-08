<?php

namespace Sabatier\Service;

use JsonSerializable;
use Override;

/**
 * Represents a single Server-Sent Event (SSE).
 *
 * Instances of this class model an event emitted over an HTTP
 * `text/event-stream` response. The event payload is automatically
 * serialized to JSON when the event is written to the stream.
 *
 * Only the event data is required. The event identifier and event
 * type are optional and follow the Server-Sent Events specification.
 *
 * @see https://html.spec.whatwg.org/multipage/server-sent-events.html
 */
final readonly class ServerSentEvent implements JsonSerializable
{
    /**
     * Creates a new server-sent event.
     *
     * @param JsonSerializable|array|string $data The event payload. This value will be JSON-encoded when the event is emitted to the client.
     * @param string|null $id An optional event identifier. If provided, the client may resume the stream from this event using the `Last-Event-ID` header when reconnecting.
     * @param string|null $event An optional event type. This allows clients to listen for specific event types using `EventSource.addEventListener`.
     */
    public function __construct(public JsonSerializable|array|string $data, public ?string $id = null, public ?string $event = null)
    {
    }

    #[Override]
    public function jsonSerialize(): JsonSerializable|string|array
    {
        return $this->data;
    }
}
