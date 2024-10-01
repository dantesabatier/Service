<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\UndefinedKeyException;
use Sabatier\Foundation\UserDefaults;
use function Sabatier\Foundation\is_password;

readonly class Authorization
{
    public AuthenticationScheme $authenticationScheme;
    public ?URLCredential $credential;
    public ?ManagedObject $user;
    public bool $isValid;

    public function __construct(public AuthorizationDescription $description)
    {
        unset($this->authenticationScheme);
        unset($this->credential);
        unset($this->user);
        unset($this->isValid);
    }

    public function __get(string $name)
    {
        $this->$name = match ($name) {
            "authenticationScheme" => $this->authenticationScheme(),
            "credential" => $this->credential(),
            "user" => $this->user(),
            "isValid" => $this->isValid(),
            default => throw new UndefinedKeyException()
        };
    }

    private function authenticationScheme(): AuthenticationScheme
    {
        return AuthenticationScheme::tryFrom($this->description->method) ?? AuthenticationScheme::basic;
    }

    private function credential(): ?URLCredential
    {
        if ($this->authenticationScheme === AuthenticationScheme::basic) {
            $components = explode(":", base64_decode($this->description->credentials));
            if (count($components) !== 2) {
                return null;
            }
            [$username, $password] = $components;
            return new URLCredential($username, $password);
        }
        if ($this->authenticationScheme === AuthenticationScheme::digest) {
            if (!($username = $this->description->parameters["username"])) {
                return null;
            }
            return new URLCredential($username);
        }
        /** @psalm-suppress RedundantCondition */
        if ($this->authenticationScheme === AuthenticationScheme::bearer) {
            if (!($key = UserDefaults::standard()->string(JWTPrivateKeyPreferenceKey))) {
                return null;
            }
            $decoder = new JWTDecoder($key, Application::shared()->request->url->host);
            if (!($username = $decoder->decode($this->description->credentials)[JWTDataField])) {
                return null;
            }
            return new URLCredential($username);
        }
        return null;
    }

    private function user(): ?ManagedObject
    {
        try {
            $application = Application::shared();
            /** @var string|null $username */
            $username = $this->credential?->user ?? $application->session->valueForKey("user");
            if (!$username) {
                return null;
            }
            $fetchRequest = new FetchRequest("User");
            $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($username));
            return $application->persistentContainer->viewContext->fetch($fetchRequest)->first?->serialized($application->serialization);
        } catch (Exception) {
            return null;
        }
    }

    private function isValid(): bool
    {
        if (!($credential = $this->credential)) {
            return false;
        }
        if (!($user = $this->user)) {
            return false;
        }
        /** @var string $password */
        $password = $user->valueForKey("password") ?? "";
        if ($this->authenticationScheme === AuthenticationScheme::basic) {
            if (is_password($password)) {
                return password_verify((string)$credential->password, $password);
            }
            return $credential->password === $password;
        }
        if ($this->authenticationScheme === AuthenticationScheme::digest) {
            $parameters = $this->description->parameters;
            if (!($username = $parameters["username"]) || !($uri = $parameters["uri"]) || !($nonce = $parameters["nonce"]) || !($nc = $parameters["nc"]) || !($cnonce = $parameters["cnonce"]) || !($qop = $parameters["qop"]) || ($parameters["algorithm"] !== "SHA-256")) {
                return false;
            }
            $request = $this->description->request;
            $HA1 = hash("sha256", "$username:{$request->url->host}:$password");
            $HA2 = hash("sha256", "$request->httpMethod:$uri");
            $response = hash("sha256", "$HA1:$nonce:$nc:$cnonce:$qop:$HA2");
            return $parameters["response"] === $response;
        }
        return $credential->user === $user->valueForKey("username");
    }
}