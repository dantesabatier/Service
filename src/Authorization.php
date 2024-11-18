<?php

namespace Sabatier\Service;

use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;

abstract class Authorization
{
    abstract public ?URLCredential $credential {
        get;
    }
    abstract public bool $isValid {
        get;
    }
    public ?ManagedObject $user {
        get {
            $application = Application::shared();
            /** @var string|null $username */
            $username = $this->credential?->user;
            if (!$username) {
                return null;
            }
            $context = $application->persistentContainer->viewContext;
            $fetchRequest = new FetchRequest();
            $fetchRequest->entity = EntityDescription::entity("User", $context);
            $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($username));
            return $context->fetch($fetchRequest)->first?->serialized($application->serialization);
        }
    }

    public function __construct(public readonly string $data)
    {
    }
}
