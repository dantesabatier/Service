<?php

namespace Sabatier\Service;

use JsonSerializable;
use Override;

final readonly class ServerSentEvent implements JsonSerializable
{
    public function __construct(public JsonSerializable|array|string $data, public ?string $id = null, public ?string $event = null)
    {
    }

    #[Override]
    public function jsonSerialize(): JsonSerializable|string|array
    {
        return $this->data;
    }
}
