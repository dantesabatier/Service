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
use function Sabatier\Foundation\localized_string;
use stdClass;

/**
 * Translates HTTP request parameters into a {@code PersistentHistoryChangeRequest}.
 *
 * GET scoping parameters: {@code afterDate} (date string), {@code afterTransaction}
 * (int), {@code afterToken} (base64-encoded JSON), {@code resultType} (int raw value
 * of {@code PersistentHistoryResultType}). DELETE scoping parameters:
 * {@code beforeDate}, {@code beforeTransaction}, {@code beforeToken}. At least one
 * method-appropriate scoping parameter must carry a non-empty value.
 *
 * @internal
 */
final class PersistentHistoryChangeRequestAdapter
{
    public PersistentHistoryChangeRequest $changeRequest {
        get {
            $parameters = $this->request->parameters;
            if ($this->request->httpMethod === HTTPRequestMethod::delete) {
                if ($this->hasScopeValue($date = $parameters[PersistentHistoryBeforeDateKey])) {
                    return PersistentHistoryChangeRequest::deleteHistoryBeforeDate(Date::dateWithTimeIntervalSince1970((float)strtotime((string)$date)));
                }
                if ($this->hasScopeValue($transactionNumber = $parameters[PersistentHistoryBeforeTransactionKey])) {
                    return PersistentHistoryChangeRequest::deleteHistoryBeforeTransaction(new PersistentHistoryTransaction(new Dictionary([PersistentHistoryTransactionNumberKey => (int)(string)$transactionNumber])));
                }
                if ($this->hasScopeValue($historyToken = $parameters[PersistentHistoryBeforeTokenKey])) {
                    return PersistentHistoryChangeRequest::deleteHistoryBeforeToken($this->tokenFromParameter((string)$historyToken));
                }
                throw new BadRequestException(localized_string("Missing scoping parameter"));
            }
            if ($this->hasScopeValue($date = $parameters[PersistentHistoryAfterDateKey])) {
                $changeRequest = PersistentHistoryChangeRequest::fetchHistoryAfterDate(Date::dateWithTimeIntervalSince1970((float)strtotime((string)$date)));
            } elseif ($this->hasScopeValue($transactionNumber = $parameters[PersistentHistoryAfterTransactionKey])) {
                $changeRequest = PersistentHistoryChangeRequest::fetchHistoryAfterTransaction(new PersistentHistoryTransaction(new Dictionary([PersistentHistoryTransactionNumberKey => (int)(string)$transactionNumber])));
            } elseif ($this->hasScopeValue($historyToken = $parameters[PersistentHistoryAfterTokenKey])) {
                $changeRequest = PersistentHistoryChangeRequest::fetchHistoryAfterToken($this->tokenFromParameter((string)$historyToken));
            } else {
                throw new BadRequestException(localized_string("Missing scoping parameter"));
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

    private function hasScopeValue(mixed $value): bool
    {
        return $value !== null && (!is_string($value) || trim($value) !== "");
    }

    private function tokenFromParameter(string $raw): PersistentHistoryToken
    {
        return new PersistentHistoryToken(Dictionary::dictionaryWithArray(json_decode(base64_decode($raw)) ?? new stdClass())->mapValues(fn(int|string $value): Number => new Number($value)));
    }
}
