<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\UndefinedKeyException;

readonly class Authorization
{
    public ?URLCredential $credential;
    public ?ManagedObject $user;

    public function __construct(public AuthenticationScheme $scheme, public string $parametersView)
    {
        unset($this->credential);
        unset($this->user);
    }

    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            "credential" => (function (): ?URLCredential {
                return match ($this->scheme) {
                    AuthenticationScheme::basic => (function (): ?URLCredential {
                        $components = explode(":", base64_decode($this->parametersView));
                        if (count($components) !== 2) {
                            return null;
                        }
                        [$username, $password] = $components;
                        return new URLCredential($username, $password);
                    })(),
                    default => null
                };
            })(),
            "user" => (function (): ?ManagedObject {
                if (!($credential = $this->credential)) {
                    return null;
                }
                $username = $credential->user;
                $application = Application::shared();
                $serialization = $application->serialization;
                $context = $application->persistentContainer->viewContext;
                $processInfo = ProcessInfo::processInfo();
                $environment = $processInfo->environment;
                /** @var string $entityName */
                $entityName = $environment["USER_ENTITY_NAME"] ?? "User";
                /** @var FetchRequest<ManagedObject> $fetchRequest */
                $fetchRequest = new FetchRequest();
                $fetchRequest->entity = EntityDescription::entity($entityName, $context);
                $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($username));
                try {
                    return $context->fetch($fetchRequest)->first()?->serialized($serialization);
                } catch (Exception) {
                    return null;
                }
            })(),
            default => throw new UndefinedKeyException("<Authorization is not key value coding compliant for the key \"$name\"")
        };
    }
}
