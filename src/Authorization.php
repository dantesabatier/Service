<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\UndefinedKeyException;
use Sabatier\Foundation\UserDefaults;
use function Sabatier\Foundation\is_password;

readonly class Authorization
{
    public string $name;
    public string $data;
    /** @var Dictionary<string> */
    public Dictionary $parameters;
    public AuthenticationScheme $scheme;
    private ?URLCredential $credential;
    public ?ManagedObject $user;
    public bool $isValid;

    public function __construct(public string $rawValue)
    {
        unset($this->parameters);
        unset($this->scheme);
        unset($this->credential);
        unset($this->user);
        unset($this->isValid);
        $components = explode(" ", $this->rawValue);
        if (count($components) !== 2) {
            $components = [AuthenticationScheme::basic->value, ""];
        }
        [$name, $data] = $components;
        $this->name = $name;
        $this->data = $data;
    }

    /**
     * @throws Exception
     */
    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            "scheme" => AuthenticationScheme::tryFrom($this->name) ?? AuthenticationScheme::basic,
            "parameters" => (function () {
                preg_match_all("/(username|uri|nonce|nc|cnonce|qop|algorithm|response|opaque)=['\"]?([^'\",]+)/", $this->data, $matches);
                return new Dictionary(array_combine($matches[1], $matches[2]));
            })(),
            "credential" => $this->credential(),
            "user" => $this->user(),
            "isValid" => $this->isValid(),
            default => throw new UndefinedKeyException()
        };
    }

    private function credential(): ?URLCredential
    {
        if ($this->scheme === AuthenticationScheme::basic) {
            $components = explode(":", base64_decode($this->data));
            if (count($components) !== 2) {
                return null;
            }
            [$username, $password] = $components;
            return new URLCredential($username, $password);
        }
        if ($this->scheme === AuthenticationScheme::digest) {
            if (!($username = $this->parameters["username"])) {
                return null;
            }
            return new URLCredential($username);
        }
        if (!($key = UserDefaults::standard()->string(JWTPrivateKeyPreferenceKey))) {
            return null;
        }
        $decoder = new JSONWebTokenDecoder($key, Application::shared()->request->url->host);
        /** @var string|null $username */
        $username = $decoder->decode($this->data)->sec;
        if (!$username) {
            return null;
        }
        return new URLCredential($username);
    }

    /**
     * @throws Exception
     */
    private function user(): ?ManagedObject
    {
        $application = Application::shared();
        /** @var string|null $username */
        $username = $this->credential?->user ?? $application->session->valueForKey("user");
        if (!$username) {
            return null;
        }
        $context = $application->persistentContainer->viewContext;
        $fetchRequest = new FetchRequest();
        $fetchRequest->entity = EntityDescription::entity("User", $context);
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($username));
        return $context->fetch($fetchRequest)->first?->serialized($application->serialization);
    }

    private function validateBasicAuthenticationScheme(URLCredential $credential, string $password): bool
    {
        if (is_password($password)) {
            return password_verify((string)$credential->password, $password);
        }
        return $credential->password === $password;
    }

    private function validateDigestAuthenticationScheme(string $password): bool
    {
        $parameters = $this->parameters;
        if (!($username = $parameters["username"]) || !($uri = $parameters["uri"]) || !($nonce = $parameters["nonce"]) || !($nc = $parameters["nc"]) || !($cnonce = $parameters["cnonce"]) || !($qop = $parameters["qop"]) || ($parameters["algorithm"] !== "SHA-256")) {
            return false;
        }
        $request = Application::shared()->request;
        $HA1 = hash("sha256", "$username:{$request->url->host}:$password");
        $HA2 = hash("sha256", "$request->httpMethod:$uri");
        $response = hash("sha256", "$HA1:$nonce:$nc:$cnonce:$qop:$HA2");
        return $parameters["response"] === $response;
    }

    private function validateBearerAuthenticationScheme(URLCredential $credential, ManagedObject $user): bool
    {
        return $credential->user === $user->valueForKey("username");
    }

    private function validate(ManagedObject $user, URLCredential $credential): bool
    {
        /** @var string $password */
        $password = $user->valueForKey("password") ?? "";
        if ($this->scheme === AuthenticationScheme::basic) {
            return $this->validateBasicAuthenticationScheme($credential, $password);
        }
        if ($this->scheme === AuthenticationScheme::digest) {
            return $this->validateDigestAuthenticationScheme($password);
        }
        return $this->validateBearerAuthenticationScheme($credential, $user);
    }

    private function isValid(): bool
    {
        if (!($credential = $this->credential)) {
            return false;
        }
        if (!($user = $this->user)) {
            return false;
        }
        return $this->validate($user, $credential);
    }
}
