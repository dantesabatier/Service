<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\ProcessInfo;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;
use const Sabatier\Foundation\Networking\URLAuthenticationMethodDefault;

class ProtectionSpace extends Responder
{
    public readonly string $authenticationMethod;
    public readonly ?JSONWebToken $token;

    public function __construct()
    {
        parent::__construct();
        unset($this->authenticationMethod);
        unset($this->token);
        $this->allowedMethods = new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::options]);
        $this->contentType = "application/json; charset=utf-8";
    }

    public function __get(string $name)
    {
        if ($name == "authenticationMethod") {
            $this->$name = (($string = $this->request->valueForHttpHeaderField("Authorization")) && ($index = strpos($string, " ")) && ($authenticationMethod = substring_to_index($string, $index))) ? $authenticationMethod : URLAuthenticationMethodDefault;
            return $this->$name;
        } elseif ($name == "token") {
            $this->$name = (($string = $this->request->valueForHttpHeaderField("Authorization")) && ($index = strpos($string, " ")) && ($hash = trim(substring_from_index($string, $index))) && count(explode(".", $hash)) == 3) ? new JSONWebToken(ProcessInfo::processInfo()->environment["APPLICATION_TOKEN_KEY"] ?? fatal_error("environment variable \"APPLICATION_TOKEN_KEY\" cannot be null"), null, $hash, $this->request->url->host) : null;
            return $this->$name;
        } else {
            return parent::__get($name);
        }
    }

    /**
     * @throws Exception
     */
    #[Action("/Authenticate")]
    public function authenticate(): void
    {
        $environment = ProcessInfo::processInfo()->environment;
        /** @var string $key */
        $key = $environment["APPLICATION_TOKEN_KEY"] ?? fatal_error("environment variable \"APPLICATION_TOKEN_KEY\" cannot be null");
        /** @var string $entityName */
        $entityName = $environment["APPLICATION_USERS_ENTITY_NAME"] ?? fatal_error("environment variable \"APPLICATION_USERS_ENTITY_NAME\" cannot be null");
        /** @var int $validity */
        $validity = $environment["APPLICATION_TOKEN_VALIDITY"] ?? 8;
        $authenticationMethod = $this->authenticationMethod;
        if ($authenticationMethod !== URLAuthenticationMethodDefault && $authenticationMethod !== "Bearer") {
            throw new UnauthorizedException();
        }
        $httpBody = $this->request->httpBody ?? "[]";
        /** @var array<string, string> $array */
        $array = json_decode($httpBody, true, 512, JSON_THROW_ON_ERROR);
        if (!$array) {
            throw new BadRequestException();
        }
        /** @var string|null $username */
        $username = $array["username"] ?? null;
        /** @var string|null $password */
        $password = $array["password"] ?? null;
        if (!$username || !$password) {
            throw new BadRequestException();
        }
        /** @var FetchRequest<ManagedObject> $fetchRequest */
        $fetchRequest = new FetchRequest();
        $fetchRequest->entity = EntityDescription::entity($entityName, $this->managedObjectContext);
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($username));
        $fetchRequest->propertiesToFetch = new ArrayClass(["username", "password"]); // @phpstan-ignore-line
        if (!($user = $this->managedObjectContext->fetch($fetchRequest)->first()?->serialized($this->serialization)) || !password_verify($password, $user->valueForKey("password"))) {
            throw new UnauthorizedException();
        }
        $date = new Date();
        $this->token = new JSONWebToken($key, ["iat" => $date->timeIntervalSinceReferenceDate, "jti" => base64_encode(random_bytes(16)), "iss" => $this->request->url->host, "nbf" => $date->timeIntervalSinceReferenceDate, "exp" => $date->addingTimeInterval(60 * 60 * $validity)->timeIntervalSinceReferenceDate, "username" => $username], null, $this->request->url->host);
        $this->content = json_encode($this->token, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws Exception
     */
    #[Action("/Me", HTTPRequestMethod::get)]
    public function me(): void
    {
        $environment = ProcessInfo::processInfo()->environment;
        /** @var string $entityName */
        $entityName = $environment["APPLICATION_USERS_ENTITY_NAME"] ?? fatal_error("environment variable \"APPLICATION_USERS_ENTITY_NAME\" cannot be null");
        if (!($username = $this->token?->payload?->username)) {
            throw new UnauthorizedException();
        }
        /** @var Dictionary<mixed> $serialization */
        $serialization = $this->serialization ?? new Dictionary();
        $serialization["username"] = AttributeType::string;
        $serialization->removeValueForKey("password");
        /** @var FetchRequest<ManagedObject> $fetchRequest */
        $fetchRequest = new FetchRequest();
        $fetchRequest->entity = EntityDescription::entity($entityName, $this->managedObjectContext);
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($username));
        $fetchRequest->serialization = $serialization;
        if (!($user = $this->managedObjectContext->fetch($fetchRequest)->first()?->serialized($this->serialization))) {
            throw new NotFoundException();
        }
        $this->content = json_encode($user, JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * @throws Exception
     */
    #[Action("/Logout")]
    public function logout(): void
    {
        $this->content = json_encode(true, JSON_THROW_ON_ERROR);
    }

    public function response(): HTTPURLResponse
    {
        switch ($this->request->httpMethod) {
            case HTTPRequestMethod::get:
            case HTTPRequestMethod::post:
                if (!$this->token?->isValid) {
                    throw new UnauthorizedException();
                }
                break;
            case HTTPRequestMethod::options:
                break;
            default:
                throw new MethodNotAllowedException();
        }
        return parent::response();
    }
}
