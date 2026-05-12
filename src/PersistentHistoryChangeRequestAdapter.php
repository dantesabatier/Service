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
            $params = $this->request->parameters;
            if ($this->request->httpMethod === HTTPRequestMethod::delete) {
                if ($date = $params["beforeDate"]) {
                    return PersistentHistoryChangeRequest::deleteHistoryBeforeDate(new Date((float)strtotime((string)$date)));
                }
                if ($n = $params["beforeTransaction"]) {
                    return PersistentHistoryChangeRequest::deleteHistoryBeforeTransaction(new PersistentHistoryTransaction(new Dictionary(["transactionNumber" => (int)(string)$n])));
                }
                if ($raw = $params["beforeToken"]) {
                    return PersistentHistoryChangeRequest::deleteHistoryBeforeToken($this->tokenFromParameter((string)$raw));
                }
                return PersistentHistoryChangeRequest::deleteHistoryBeforeToken(null);
            }
            if ($date = $params["afterDate"]) {
                $changeRequest = PersistentHistoryChangeRequest::fetchHistoryAfterDate(new Date((float)strtotime((string)$date)));
            } elseif ($n = $params["afterTransaction"]) {
                $changeRequest = PersistentHistoryChangeRequest::fetchHistoryAfterTransaction(new PersistentHistoryTransaction(new Dictionary(["transactionNumber" => (int)(string)$n])));
            } elseif ($raw = $params["afterToken"]) {
                $changeRequest = PersistentHistoryChangeRequest::fetchHistoryAfterToken($this->tokenFromParameter((string)$raw));
            } else {
                $changeRequest = PersistentHistoryChangeRequest::fetchHistoryAfterToken(null);
            }
            if ($resultType = $params["resultType"]) {
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
