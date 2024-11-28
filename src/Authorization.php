<?php

namespace Sabatier\Service;

use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
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
    public ?Authenticatable $user {
        get {
            if (!($username = $this->credential?->user)) {
                return null;
            }
            $context = Application::shared()->persistentContainer->viewContext;
            /** @var FetchRequest<Authenticatable> $fetchRequest */
            $fetchRequest = new FetchRequest();
            $fetchRequest->entity = EntityDescription::entity("User", $context);
            $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($username));
            return $context->fetch($fetchRequest)->first?->serialized(Application::shared()->request->serialization);
        }
    }

    public function __construct(public readonly string $credentials, #[ExpectedValues(valuesFromClass: HTTPRequestMethod::class)] public string $method = HTTPRequestMethod::get, public readonly ?string $host = null, public readonly ?int $port = null)
    {
    }
}
