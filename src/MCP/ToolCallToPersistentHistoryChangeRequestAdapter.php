<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\CoreData\PersistentHistoryChangeRequest;
use Sabatier\CoreData\PersistentHistoryResultType;
use Sabatier\CoreData\PersistentHistoryToken;
use Sabatier\CoreData\PersistentHistoryTransaction;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Number;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\Service\PersistentHistoryAfterDateKey;
use const Sabatier\Service\PersistentHistoryAfterTokenKey;
use const Sabatier\Service\PersistentHistoryAfterTransactionKey;
use const Sabatier\Service\PersistentHistoryBeforeDateKey;
use const Sabatier\Service\PersistentHistoryBeforeTokenKey;
use const Sabatier\Service\PersistentHistoryBeforeTransactionKey;
use const Sabatier\Service\PersistentHistoryResultTypeKey;
use const Sabatier\Service\PersistentHistoryTransactionNumberKey;

/**
 * Translates the arguments of a tool call into a persistent history change request, as {@see \Sabatier\Service\PersistentHistoryChangeRequestAdapter} translates an HTTP request. The arguments are named after the Core Data factory methods they select — `afterDate`, `afterToken`, `afterTransaction` for a fetch, `beforeDate`, `beforeToken`, `beforeTransaction` for a purge — so the direction is in the name rather than in an argument of its own.
 *
 * @internal
 */
final class ToolCallToPersistentHistoryChangeRequestAdapter
{
    public PersistentHistoryChangeRequest $changeRequest {
        get => $this->changeRequest ??= $this->build();
    }

    /**
     * @param Dictionary<mixed> $arguments The arguments the caller supplied.
     */
    public function __construct(private readonly Dictionary $arguments)
    {
    }

    private function build(): PersistentHistoryChangeRequest
    {
        $arguments = $this->arguments;
        if ($date = $arguments[PersistentHistoryBeforeDateKey]) {
            return PersistentHistoryChangeRequest::deleteHistoryBeforeDate($this->date($date));
        }
        if (($transaction = $arguments[PersistentHistoryBeforeTransactionKey]) !== null) {
            return PersistentHistoryChangeRequest::deleteHistoryBeforeTransaction($this->transaction($transaction));
        }
        if ($token = $arguments[PersistentHistoryBeforeTokenKey]) {
            return PersistentHistoryChangeRequest::deleteHistoryBeforeToken($this->token($token));
        }
        $changeRequest = $this->fetchScope();
        if (($resultType = $arguments[PersistentHistoryResultTypeKey]) !== null) {
            $changeRequest->resultType = PersistentHistoryResultType::tryFrom((int)(string)$resultType) ?? fatal_error("Invalid resultType \"$resultType\".");
        }
        return $changeRequest;
    }

    private function fetchScope(): PersistentHistoryChangeRequest
    {
        $arguments = $this->arguments;
        if ($date = $arguments[PersistentHistoryAfterDateKey]) {
            return PersistentHistoryChangeRequest::fetchHistoryAfterDate($this->date($date));
        }
        if (($transaction = $arguments[PersistentHistoryAfterTransactionKey]) !== null) {
            return PersistentHistoryChangeRequest::fetchHistoryAfterTransaction($this->transaction($transaction));
        }
        if ($token = $arguments[PersistentHistoryAfterTokenKey]) {
            return PersistentHistoryChangeRequest::fetchHistoryAfterToken($this->token($token));
        }
        fatal_error("A scope is required: \"" . PersistentHistoryAfterDateKey . "\", \"" . PersistentHistoryAfterTransactionKey . "\" or \"" . PersistentHistoryAfterTokenKey . "\" to fetch, \"" . PersistentHistoryBeforeDateKey . "\", \"" . PersistentHistoryBeforeTransactionKey . "\" or \"" . PersistentHistoryBeforeTokenKey . "\" to purge.");
    }

    private function date(mixed $date): Date
    {
        return Date::dateWithTimeIntervalSince1970((float)strtotime((string)$date));
    }

    private function transaction(mixed $transaction): PersistentHistoryTransaction
    {
        return new PersistentHistoryTransaction(new Dictionary([PersistentHistoryTransactionNumberKey => (int)(string)$transaction]));
    }

    private function token(mixed $token): PersistentHistoryToken
    {
        $token instanceof Dictionary ?: fatal_error("A token is an object mapping store identifier to token number.");
        return new PersistentHistoryToken($token->mapValues(fn(int|string $value): Number => new Number($value)));
    }
}
