<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\CoreData\PersistentHistoryResult;
use Sabatier\CoreData\PersistentHistoryResultType;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use const Sabatier\Service\PersistentHistoryResultTypeKey;
use const Sabatier\Service\ServiceResponseCountKey;
use const Sabatier\Service\ServiceResponseStatusKey;

/** @internal */
final class FetchHistoryTool extends PersistentHistoryTool
{
    #[Override]
    public string $name {
        get => "fetch_history";
    }
    #[Override]
    public bool $isReadOnly {
        get => true;
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => [...self::fetchScopeSchema, ...self::transactionFilterSchema, PersistentHistoryResultTypeKey => ["type" => "integer", "description" => "Shape of the returned history, the raw value of PersistentHistoryResultType: 0 statusOnly, 1 objectIDs, 2 count, 3 transactionsOnly, 4 changesOnly, 5 transactionsAndChanges. Defaults to transactionsAndChanges."]],
        ];
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    #[Override]
    protected function executeCore(Dictionary $arguments): ArrayClass
    {
        return $this->jsonResult($this->shape($this->perform($arguments)));
    }

    /**
     * A status and a count are scalars, and are reported under the key HTTP reports them under; every other result type already answers with the history itself.
     */
    protected function shape(PersistentHistoryResult $result): mixed
    {
        return match ($result->resultType) {
            PersistentHistoryResultType::statusOnly => new Dictionary([ServiceResponseStatusKey => $result->result]),
            PersistentHistoryResultType::count => new Dictionary([ServiceResponseCountKey => $result->result]),
            default => $result->result,
        };
    }
}
