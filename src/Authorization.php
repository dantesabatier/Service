<?php

namespace Sabatier\Service;

use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ComparisonPredicate;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Expression;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\URLCredential;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\substring_from_index;

/**
 * Class Authorization
 * @package Sabatier\Service
 */
class Authorization extends ObjectClass
{
    public readonly ?JSONWebToken $token;
    public readonly ?URLCredential $credential;
    public readonly ?ManagedObject $user;

    public function __construct(public readonly Service $service)
    {
        unset($this->token);
        unset($this->credential);
        unset($this->user);
    }

    public function __get(string $name)
    {
        if ($name == 'token') {
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->$name = (($string = $this->service->request->valueForHttpHeaderField('Authorization')) && ($index = strpos($string, ' ')) && ($hash = trim(substring_from_index($string, $index))) && count(explode('.', $hash)) == 3) ? new JSONWebToken(ProcessInfo::processInfo()->environment['SERVICE_TOKEN_KEY'] ?? fatal_error("environment variable \"SERVICE_TOKEN_KEY\" cannot be null"), null, $hash) : null;
            return $this->$name;
        } elseif ($name == 'credential') {
            $credential = null;
            if (($body = $this->service->request->httpBody) && ($array = json_decode($body, true))) {
                /** @var string|null $username */
                $username = $array['username'] ?? null;
                /** @var string|null $password */
                $password = $array['password'] ?? null;
                if ($username && $password) {
                    $credential = new URLCredential($username, $password);
                }
            } elseif ($token = $this->token) {
                /** @var object|null $payload */
                $payload = $token->payload;
                /** @var string|null $username */
                $username = $payload?->username;
                if ($username) {
                    $credential = new URLCredential($username);
                }
            }
            $this->$name = $credential;
            return $this->$name;
        } elseif ($name == 'user') {
            $user = null;
            if ($username = $this->credential?->user) {
                /** @var Dictionary<mixed> $serialization */
                $serialization = new Dictionary($this->service->serialization ?? []);
                $serialization['username'] = AttributeType::string;
                $serialization['password'] = AttributeType::string;
                $context = $this->service->persistentContainer->viewContext;
                /** @var FetchRequest<ManagedObject> $fetchRequest */
                $fetchRequest = new FetchRequest();
                /** @noinspection PhpUnhandledExceptionInspection */
                $fetchRequest->entity = EntityDescription::entity(ProcessInfo::processInfo()->environment['SERVICE_USERS_ENTITY_NAME'] ?? fatal_error("environment variable \"SERVICE_USERS_ENTITY_NAME\" cannot be null"), $context);
                $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath('username'), Expression::expressionForConstantValue($username));
                $fetchRequest->serialization = $serialization;
                /** @noinspection PhpUnhandledExceptionInspection */
                $user = $context->fetch($fetchRequest)->first()?->serialized($this->service->serialization);
            }
            $this->$name = $user;
            return $this->$name;
        } else {
            return $this->valueForUndefinedKey($name);
        }
    }
}
