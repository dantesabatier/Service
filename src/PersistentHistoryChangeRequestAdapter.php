<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\CoreData\PersistentHistoryChangeRequest;
use Sabatier\CoreData\PersistentHistoryResultType;
use Sabatier\CoreData\PersistentHistoryToken;
use Sabatier\CoreData\PersistentHistoryTransaction;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Number;

/**
 * Translates HTTP request parameters into a {@code PersistentHistoryChangeRequest}.
 *
 * GET scoping parameters: {@code afterDate} (date string), {@code afterTransaction}
 * (int), {@code afterToken} (base64-encoded JSON), {@code resultType} (int raw value
 * of {@code PersistentHistoryResultType}). DELETE scoping parameters:
 * {@code beforeDate}, {@code beforeTransaction}, {@code beforeToken}. All parameters
 * are optional; omitting them targets the full history.
 *
 * @internal
 */
final class PersistentHistoryChangeRequestAdapter
{
    public PersistentHistoryChangeRequest $changeRequest {
        get {
            $parameters = $this->request->parameters;
            if ($this->request->httpMethod === HTTPRequestMethod::delete) {
                $parameters->contains(fn(mixed $value, string $key): bool => match ($key) {
                    PersistentHistoryBeforeDateKey, PersistentHistoryBeforeTransactionKey, PersistentHistoryBeforeTokenKey => true,
                    default => false
                }) ?: throw new BadRequestException("Missing scoping parameter");
                if ($date = $parameters[PersistentHistoryBeforeDateKey]) {
                    return PersistentHistoryChangeRequest::deleteHistoryBeforeDate(new Date((float)strtotime((string)$date)));
                }
                if ($transactionNumber = $parameters[PersistentHistoryBeforeTransactionKey]) {
                    return PersistentHistoryChangeRequest::deleteHistoryBeforeTransaction(new PersistentHistoryTransaction(new Dictionary([PersistentHistoryTransactionNumberKey => (int)(string)$transactionNumber])));
                }
                if ($historyToken = $parameters[PersistentHistoryBeforeTokenKey]) {
                    return PersistentHistoryChangeRequest::deleteHistoryBeforeToken($this->tokenFromParameter((string)$historyToken));
                }
                return PersistentHistoryChangeRequest::deleteHistoryBeforeToken(null);
            }
            $parameters->contains(fn(mixed $value, string $key): bool => match ($key) {
                PersistentHistoryAfterDateKey, PersistentHistoryAfterTransactionKey, PersistentHistoryAfterTokenKey => true,
                default => false
            }) ?: throw new BadRequestException("Missing scoping parameter");
            if ($date = $parameters[PersistentHistoryAfterDateKey]) {
                $changeRequest = PersistentHistoryChangeRequest::fetchHistoryAfterDate(new Date((float)strtotime((string)$date)));
            } elseif ($transactionNumber = $parameters[PersistentHistoryAfterTransactionKey]) {
                $changeRequest = PersistentHistoryChangeRequest::fetchHistoryAfterTransaction(new PersistentHistoryTransaction(new Dictionary([PersistentHistoryTransactionNumberKey => (int)(string)$transactionNumber])));
            } elseif ($historyToken = $parameters[PersistentHistoryAfterTokenKey]) {
                $changeRequest = PersistentHistoryChangeRequest::fetchHistoryAfterToken($this->tokenFromParameter((string)$historyToken));
            } else {
                $changeRequest = PersistentHistoryChangeRequest::fetchHistoryAfterToken(null);
            }
            if ($resultType = $parameters[PersistentHistoryResultTypeKey]) {
                $changeRequest->resultType = PersistentHistoryResultType::from((int)(string)$resultType);
            }
            return $changeRequest;
        }
    }

    public function __construct(private readonly Request $request)
    {
    }

    private function tokenFromParameter(string $raw): PersistentHistoryToken
    {
        return new PersistentHistoryToken(Dictionary::dictionaryWithArray(json_decode(base64_decode($raw), true) ?? [])->mapValues(fn(int|string $value): Number => new Number($value)));
    }
}
