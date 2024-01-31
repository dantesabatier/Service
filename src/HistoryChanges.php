<?php

namespace Sabatier\Service;

use Sabatier\CoreData\PersistentHistoryChangeRequest;
use Sabatier\CoreData\PersistentHistoryResult;
use Sabatier\CoreData\PersistentHistoryResultType;
use Sabatier\CoreData\PersistentHistoryTransaction;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Predicates\Predicate;

#[Endpoint]
class HistoryChanges extends Responder
{
    public function response(): HTTPURLResponse
    {
        switch ($this->request->httpMethod) {
            case HTTPRequestMethod::get:
                $context = $this->managedObjectContext;
                $request = PersistentHistoryChangeRequest::fetchHistoryAfterDate(Date::distantPast());
                $request->resultType = PersistentHistoryResultType::count;
                $fetchRequest = PersistentHistoryTransaction::fetchRequest();
                if ($fetchRequest) {
                    $fetchRequest->predicate = Predicate::format("%K = %s OR %K != %s", new ArrayClass(["author", null, "author", $context->transactionAuthor]));
                    $request->fetchRequest = $fetchRequest;
                }
                /** @var PersistentHistoryResult $result */
                $result = $context->execute($request);
                $this->content = json_encode($result->result, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
                $this->contentType = "application/json; charset=utf-8";
                break;
            case HTTPRequestMethod::post:
            case HTTPRequestMethod::put:
            case HTTPRequestMethod::patch:
            case HTTPRequestMethod::delete:
            case HTTPRequestMethod::options:
                break;
            default:
                throw new MethodNotAllowedException();
        }
        return new HTTPURLResponse($this->request->url);
    }
}
