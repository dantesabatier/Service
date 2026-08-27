<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\PersistentHistoryChangeRequest;
use Sabatier\CoreData\PersistentHistoryResult;
use Sabatier\CoreData\PersistentHistoryResultType;
use Sabatier\CoreData\PersistentHistoryToken;
use Sabatier\CoreData\PersistentHistoryTransaction;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Number;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\MCP\Response\ContentItem;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\Service\PersistentHistoryTransactionNumberKey;
use const Sabatier\Service\ServiceResponseCountKey;
use const Sabatier\Service\ServiceResponseStatusKey;

/** @internal */
final class PersistentHistoryTool extends AbstractTool
{
    private const array operations = ["fetch", "purge"];

    /** @var Dictionary<PersistentHistoryResultType> */
    private Dictionary $resultTypes {
        get => $this->resultTypes ??= new Dictionary([
            "statusOnly" => PersistentHistoryResultType::statusOnly,
            "objectIDs" => PersistentHistoryResultType::objectIDs,
            "count" => PersistentHistoryResultType::count,
            "transactionsOnly" => PersistentHistoryResultType::transactionsOnly,
            "changesOnly" => PersistentHistoryResultType::changesOnly,
            "transactionsAndChanges" => PersistentHistoryResultType::transactionsAndChanges,
        ]);
    }
    /** @var ArrayClass<string> */
    private ArrayClass $resultTypeNames {
        get => $this->resultTypeNames ??= $this->resultTypes->keys;
    }

    #[Override]
    public string $name {
        get => "persistent_history";
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => [
                "operation" => ["type" => "string", "enum" => self::operations, "description" => "\"fetch\" to read history, \"purge\" to permanently delete it."],
                "date" => ["type" => "string", "description" => "ISO 8601 date. fetch: history after this date; purge: history before this date."],
                "transaction" => ["type" => "integer", "description" => "Transaction number boundary. fetch: history after it; purge: history before it."],
                "token" => ["type" => "object", "description" => "Persistent history token as a map of store identifier (string) to token number."],
                "entity" => ["type" => "string", "enum" => ["PersistentHistoryTransaction", "PersistentHistoryChange"], "description" => "History entity the predicate filters on. Defaults to PersistentHistoryTransaction (filter changes via the \"changes\" relationship, e.g. \"ANY changes.changeType = %d\"); use PersistentHistoryChange to filter change rows directly. Ignored when no predicate is given."],
                "predicate" => ["type" => "string", "description" => "Optional. NSPredicate format string filtering the history in scope by the chosen entity's properties (e.g. \"author\", \"contextName\", \"bundleID\", \"changes.changeType\"). Use %K for key paths, %@ for strings, %d for integers."],
                "arguments" => ["type" => "array", "items" => ["type" => ["string", "number", "boolean", "array"]], "description" => "Positional arguments for the predicate placeholders, one per placeholder in order."],
                "resultType" => ["type" => "string", "enum" => $this->resultTypeNames->array, "description" => "fetch only, ignored for purge. Shape of the returned history. Defaults to transactionsAndChanges."],
            ],
            "required" => ["operation"],
        ];
    }

    #[Override]
    public function isReadOnlyCall(Dictionary $arguments): bool
    {
        return $arguments["operation"] === "fetch";
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        /** @var string $operation */
        $operation = $arguments["operation"] ?? fatal_error("operation is required");
        in_array($operation, self::operations, true) ?: fatal_error("Invalid operation \"$operation\". Allowed: " . new ArrayClass(self::operations)->join(", ") . ".");
        $this->enforceEntityAuthorization("history", $operation === "purge" ? AuthorizationType::delete : AuthorizationType::read);
        $changeRequest = $operation === "purge" ? $this->purgeRequest($arguments) : $this->fetchRequestFor($arguments);
        if ($fetchRequest = $this->transactionFilter($arguments)) {
            $changeRequest->fetchRequest = $fetchRequest;
        }
        /** @var PersistentHistoryResult $result */
        $result = $this->context->execute($changeRequest);
        if ($operation === "purge") {
            return $this->jsonResult(["purged" => true]);
        }
        return $this->jsonResult($this->shapeResult($result));
    }

    private function purgeRequest(Dictionary $arguments): PersistentHistoryChangeRequest
    {
        if ($date = $arguments["date"]) {
            return PersistentHistoryChangeRequest::deleteHistoryBeforeDate(Date::dateWithTimeIntervalSince1970((float)strtotime((string)$date)));
        }
        if (($transaction = $arguments["transaction"]) !== null) {
            return PersistentHistoryChangeRequest::deleteHistoryBeforeTransaction($this->transaction($transaction));
        }
        if ($token = $arguments["token"]) {
            return PersistentHistoryChangeRequest::deleteHistoryBeforeToken($this->token($token));
        }
        fatal_error("purge requires a scope parameter: \"date\", \"transaction\" or \"token\".");
    }

    private function fetchRequestFor(Dictionary $arguments): PersistentHistoryChangeRequest
    {
        $changeRequest = $this->fetchScope($arguments);
        if ($resultType = $arguments["resultType"]) {
            /** @var PersistentHistoryResultType $mapped */
            $mapped = $this->resultTypes[(string)$resultType] ?? fatal_error("Invalid resultType \"$resultType\". Allowed: {$this->resultTypeNames->join(", ")}.");
            $changeRequest->resultType = $mapped;
        }
        return $changeRequest;
    }

    private function fetchScope(Dictionary $arguments): PersistentHistoryChangeRequest
    {
        if ($date = $arguments["date"]) {
            return PersistentHistoryChangeRequest::fetchHistoryAfterDate(Date::dateWithTimeIntervalSince1970((float)strtotime((string)$date)));
        }
        if (($transaction = $arguments["transaction"]) !== null) {
            return PersistentHistoryChangeRequest::fetchHistoryAfterTransaction($this->transaction($transaction));
        }
        if ($token = $arguments["token"]) {
            return PersistentHistoryChangeRequest::fetchHistoryAfterToken($this->token($token));
        }
        fatal_error("fetch requires a scope parameter: \"date\", \"transaction\" or \"token\".");
    }

    private function transactionFilter(Dictionary $arguments): ?FetchRequest
    {
        /** @var string|null $predicate */
        $predicate = $arguments["predicate"];
        if (!$predicate) {
            return null;
        }
        /** @var ArrayClass<mixed> $params */
        $params = $arguments["arguments"] ?? new ArrayClass();
        $entityName = (string)($arguments["entity"] ?? "PersistentHistoryTransaction");
        $fetchRequest = new FetchRequest();
        $fetchRequest->entity = $this->context->persistentStoreCoordinator?->managedObjectModel?->entitiesByName[$entityName] ?? fatal_error("Unknown history entity \"$entityName\". Use \"PersistentHistoryTransaction\" or \"PersistentHistoryChange\". History tracking may be disabled for the store.");
        $fetchRequest->predicate = $this->buildPredicate($predicate, $this->resolveVariables($params));
        return $fetchRequest;
    }

    private function transaction(mixed $transaction): PersistentHistoryTransaction
    {
        return new PersistentHistoryTransaction(new Dictionary([PersistentHistoryTransactionNumberKey => (int)(string)$transaction]));
    }

    private function token(mixed $token): PersistentHistoryToken
    {
        $token instanceof Dictionary ?: fatal_error("token must be an object mapping store identifier to token number.");
        return new PersistentHistoryToken($token->mapValues(fn(int|string $value): Number => new Number($value)));
    }

    private function shapeResult(PersistentHistoryResult $result): mixed
    {
        return match ($result->resultType) {
            PersistentHistoryResultType::statusOnly => new Dictionary([ServiceResponseStatusKey => $result->result]),
            PersistentHistoryResultType::count => new Dictionary([ServiceResponseCountKey => $result->result]),
            default => $result->result,
        };
    }
}
