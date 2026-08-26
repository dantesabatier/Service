<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use JsonException;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use stdClass;

/**
 * Returns the server's current date and time, with timezone.
 *
 * The generic counterpart to the data-model tools: it answers "what time is it now?" without
 * relying on the timestamp of the prompt, which an agent needs to reason about schedules,
 * deadlines and date-relative predicates. The reported time is the server's local time in its
 * configured timezone; `unix` is the absolute timestamp so the model can derive any other zone.
 *
 * Reads no entities, so it enforces no row- or column-level security.
 */
final class GetServerTimeTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "get_server_time";
    }
    #[Override]
    public bool $isReadOnly {
        get => true;
    }

    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => new stdClass(),
        ];
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws JsonException
     */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        $now = Date::now();
        return $this->jsonResult([
            "iso8601" => $now->ISO8601Format(),
            "unix" => (int)$now->timeIntervalSince1970,
            "timezone" => date_default_timezone_get(),
            "weekday" => $now->format("l"),
            "date" => $now->format("Y-m-d"),
            "time" => $now->format("H:i:s"),
        ]);
    }
}
