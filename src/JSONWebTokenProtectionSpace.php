<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\ProcessInfo;
use const Sabatier\Foundation\NotFound;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;

/** @internal */
class JSONWebTokenProtectionSpace extends ProtectionSpace
{
    public string $defaultAuthenticationMethod = self::authenticationMethodBearer;
    private ?JSONWebToken $token;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();
        $authentication = $this->request->valueForHttpHeaderField("Authorization");
        $authenticationIndex = $authentication ? (int)strpos($authentication, " ") : NotFound;
        $this->authenticationMethod = $authentication ? substring_to_index($authentication, $authenticationIndex) : null;
        $this->token = $authentication && (($hash = trim(substring_from_index($authentication, $authenticationIndex))) && count(explode(".", $hash)) == 3) ? new JSONWebToken(ProcessInfo::processInfo()->environment["APPLICATION_TOKEN_KEY"] ?? fatal_error("Environment variable \"APPLICATION_TOKEN_KEY\" cannot be null"), null, $hash, $this->request->url->host) : null;
        $this->username = $this->token?->payload?->username;
        $this->isProtectedContentAvailable = (bool)$this->token?->isValid;
        $this->allowedMethods = new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::options]);
    }

    /**
     * @throws Exception
     */
    #[Action("/Authenticate")]
    public function authenticate(): void
    {
        $environment = ProcessInfo::processInfo()->environment;
        /** @var string $key */
        $key = $environment["APPLICATION_TOKEN_KEY"] ?? fatal_error("Environment variable \"APPLICATION_TOKEN_KEY\" cannot be null");
        /** @var string $entityName */
        $entityName = $environment["APPLICATION_USERS_ENTITY_NAME"] ?? fatal_error("Environment variable \"APPLICATION_USERS_ENTITY_NAME\" cannot be null");
        /** @var int $validity */
        $validity = $environment["APPLICATION_TOKEN_VALIDITY"] ?? 8;
        if ($this->authenticationMethod != $this->defaultAuthenticationMethod) {
            throw new UnauthorizedException();
        }
        /** @var array<string, string> $body */
        $body = json_decode($this->request->httpBody ?? "[]", true, 512, JSON_THROW_ON_ERROR);
        if (!$body) {
            throw new BadRequestException();
        }
        /** @var string|null $username */
        $username = $body["username"] ?? null;
        /** @var string|null $password */
        $password = $body["password"] ?? null;
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
        $this->username = $this->token->payload?->username;
        $this->isProtectedContentAvailable = $this->token->isValid;
        $this->content = json_encode($this->token, JSON_THROW_ON_ERROR);
        $this->contentType = "application/json; charset=utf-8";
    }

    /**
     * @throws Exception
     */
    #[Action("/Me", HTTPRequestMethod::get)]
    public function me(): void
    {
        $environment = ProcessInfo::processInfo()->environment;
        /** @var string $entityName */
        $entityName = $environment["APPLICATION_USERS_ENTITY_NAME"] ?? fatal_error("Environment variable \"APPLICATION_USERS_ENTITY_NAME\" cannot be null");
        if (!($username = $this->username)) {
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
        $this->contentType = "application/json; charset=utf-8";
    }

    /**
     * @throws Exception
     */
    #[Action("/Logout")]
    public function logout(): void
    {
        $this->content = json_encode(true, JSON_THROW_ON_ERROR);
        $this->contentType = "application/json; charset=utf-8";
    }
}
