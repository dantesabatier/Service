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
use const Sabatier\Foundation\Networking\URLAuthenticationMethodHTTPBearer;

/** @internal */
class BearerAuthentication extends Authentication
{
    public function __construct()
    {
        parent::__construct();
        $this->scheme = AuthenticationScheme::bearer;
        $this->allowedMethods = new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::options]);
    }

    /**
     * @throws Exception
     */
    #[Action("/Authenticate")]
    public function authenticate(): void
    {
        if ($this->space->authenticationMethod !== URLAuthenticationMethodHTTPBearer) {
            throw new UnauthorizedException();
        }
        /** @var array<string, string> $body */
        $body = json_decode($this->request->httpBody ?? "[]", true, 512, JSON_THROW_ON_ERROR);
        /** @var string|null $username */
        $username = $body["username"] ?? null;
        /** @var string|null $password */
        $password = $body["password"] ?? null;
        if (!$username || !$password) {
            throw new BadRequestException();
        }
        $environment = ProcessInfo::processInfo()->environment;
        /** @var string $key */
        $key = $environment["JWT_KEY"] ?? "";
        /** @var string $entityName */
        $entityName = $environment["USER_ENTITY_NAME"] ?? "User";
        $validity = (int)($environment["JWT_VALIDITY"] ?? 8);
        /** @var FetchRequest<ManagedObject> $fetchRequest */
        $fetchRequest = new FetchRequest();
        $fetchRequest->entity = EntityDescription::entity($entityName, $this->managedObjectContext);
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($username));
        $fetchRequest->propertiesToFetch = new ArrayClass(["username", "password"]); // @phpstan-ignore-line
        if (!($user = $this->managedObjectContext->fetch($fetchRequest)->first()?->serialized($this->serialization)) || !password_verify($password, $user->valueForKey("password"))) {
            throw new UnauthorizedException();
        }
        $date = new Date();
        $encoder = new JWTEncoder($key);
        $token = $encoder->encode(["iat" => $date->timeIntervalSinceReferenceDate, "jti" => base64_encode(random_bytes(16)), "iss" => $this->request->url->host, "nbf" => $date->timeIntervalSinceReferenceDate, "exp" => $date->addingTimeInterval(60 * 60 * $validity)->timeIntervalSinceReferenceDate, "username" => $username]);
        $content = json_encode($token, JSON_THROW_ON_ERROR);
        $this->content = $content;
        $this->contentType = "application/json; charset=utf-8";
    }

    /**
     * @throws Exception
     */
    #[Action("/Me", HTTPRequestMethod::get)]
    public function me(): void
    {
        if (!($username = $this->credential?->user)) {
            throw new UnauthorizedException();
        }
        $environment = ProcessInfo::processInfo()->environment;
        /** @var string $entityName */
        $entityName = $environment["USER_ENTITY_NAME"] ?? "User";
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
