<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CompareOptions;
use function Sabatier\Foundation\string_begins_with;

/**
 * Check if a streamed response has started: its headers went out, so whatever is sent next can only be one more line of the stream.
 *
 * It reads what was actually sent rather than tracking it, so it holds for anything that reaches the client after the stream began, an error response or a fatal error included.
 *
 * @return bool
 */
function stream_has_started(): bool
{
    return headers_sent() && new ArrayClass(headers_list())->contains(fn(string $header): bool => string_begins_with($header, "Content-Type: application/x-ndjson", CompareOptions::caseInsensitive));
}
