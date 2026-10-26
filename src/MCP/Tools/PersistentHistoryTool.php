<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\PersistentHistoryChangeRequest;
use Sabatier\CoreData\PersistentHistoryResult;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\ToolCallToFetchRequestAdapter;
use Sabatier\Service\MCP\ToolCallToPersistentHistoryChangeRequestAdapter;
use Sabatier\Service\PersistentHistoryOperation;
use const Sabatier\Service\PersistentHistoryAfterDateKey;
use const Sabatier\Service\PersistentHistoryAfterTokenKey;
use const Sabatier\Service\PersistentHistoryAfterTransactionKey;
use const Sabatier\Service\PersistentHistoryBeforeDateKey;
use const Sabatier\Service\PersistentHistoryBeforeTokenKey;
use const Sabatier\Service\PersistentHistoryBeforeTransactionKey;

/**
 * @internal
 * @psalm-consistent-constructor
 * @phpstan-consistent-constructor
 */
abstract class PersistentHistoryTool extends AbstractTool
{
    /** @var array<array-key, mixed> The scoping arguments a fetch of history accepts, named after the Core Data factory methods they select. */
    protected const array fetchScopeSchema = [
        PersistentHistoryAfterDateKey => ["type" => "string", "description" => "ISO 8601 date. History after this date."],
        PersistentHistoryAfterTransactionKey => ["type" => "integer", "description" => "History after this transaction number."],
        PersistentHistoryAfterTokenKey => ["type" => "object", "description" => "History after this token, as a map of store identifier (string) to token number."],
    ];
    /** @var array<array-key, mixed> The scoping arguments a purge of history accepts. */
    protected const array purgeScopeSchema = [
        PersistentHistoryBeforeDateKey => ["type" => "string", "description" => "ISO 8601 date. History before this date."],
        PersistentHistoryBeforeTransactionKey => ["type" => "integer", "description" => "History before this transaction number."],
        PersistentHistoryBeforeTokenKey => ["type" => "object", "description" => "History before this token, as a map of store identifier (string) to token number."],
    ];
    /** @var array<array-key, mixed> The arguments narrowing which transactions the scope reaches, read by the same adapter an ordinary query uses. */
    protected const array transactionFilterSchema = [
        "entityName" => ["type" => "string", "enum" => ["PersistentHistoryTransaction", "PersistentHistoryChange"], "description" => "History entity the predicate filters on. PersistentHistoryTransaction filters transactions (reach their changes through the \"changes\" relationship, e.g. \"ANY changes.changeType = %d\"); PersistentHistoryChange filters change rows directly."],
        "predicate" => ["type" => "object", "description" => "Filters the history in scope by the chosen entity's properties — \"author\", \"contextName\", \"bundleID\", \"changes.changeType\": {\"format\": \"%K == %@\", \"arguments\": [...]}."],
    ];

    #[Override]
    public bool $isOpenWorld {
        get => false;
    }

    /**
     * The history resource is one: `/history` is a single endpoint, and both tools are authorized against it.
     *
     * @param Dictionary<mixed> $arguments The arguments selecting the concrete operation.
     */
    #[Override]
    public function authorizationResource(Dictionary $arguments): string
    {
        return "history";
    }

    /**
     * @param Dictionary<mixed> $arguments
     * @throws Exception
     */
    protected function perform(Dictionary $arguments): PersistentHistoryResult
    {
        return new PersistentHistoryOperation($this->context, $this->changeRequest($arguments), $this->transactionFilter($arguments))->perform();
    }

    /**
     * @param Dictionary<mixed> $arguments
     */
    protected function changeRequest(Dictionary $arguments): PersistentHistoryChangeRequest
    {
        return new ToolCallToPersistentHistoryChangeRequestAdapter($arguments)->changeRequest;
    }

    /**
     * The transactions the scope reaches are narrowed by an ordinary fetch request, exactly as `PersistentHistoryResponseStrategy` narrows them for HTTP.
     *
     * @param Dictionary<mixed> $arguments
     * @throws Exception
     */
    protected function transactionFilter(Dictionary $arguments): ?FetchRequest
    {
        if (!$arguments["predicate"]) {
            return null;
        }
        return new ToolCallToFetchRequestAdapter($arguments, $this->context)->fetchRequest;
    }
}
