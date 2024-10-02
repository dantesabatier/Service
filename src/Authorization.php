<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\UndefinedKeyException;
use Sabatier\Foundation\UserDefaults;
use function Sabatier\Foundation\is_password;

readonly class Authorization
{
    private string $name;
    private string $credentials;
    /** @var Dictionary<string> */
    private Dictionary $parameters;
    private ?URLCredential $credential;
    public AuthenticationScheme $scheme;
    public ?ManagedObject $user;
    public bool $isValid;

    public function __construct(public string $rawValue)
    {
        unset($this->parameters);
        unset($this->credential);
        unset($this->user);
        unset($this->isValid);
        unset($this->scheme);
        $components = explode(" ", $this->rawValue);
        if (count($components) !== 2) {
            $components = [AuthenticationScheme::basic->value, ""];
        }
        [$name, $credentials] = $components;
        $this->name = $name;
        $this->credentials = $credentials;
    }

    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            "scheme" => $this->scheme(),
            "parameters" => $this->parameters(),
            "credential" => $this->credential(),
            "user" => $this->user(),
            "isValid" => $this->isValid(),
            default => throw new UndefinedKeyException()
        };
    }

    private function scheme(): AuthenticationScheme
    {
        return AuthenticationScheme::tryFrom($this->name) ?? AuthenticationScheme::basic;
    }

    private function parameters(): Dictionary
    {
        preg_match_all("/(username|uri|nonce|nc|cnonce|qop|algorithm|response|opaque)=['\"]?([^'\",]+)/", $this->credentials, $matches);
        return new Dictionary(array_combine($matches[1], $matches[2]));
    }

    private function credential(): ?URLCredential
    {
        if ($this->scheme === AuthenticationScheme::basic) {
            $components = explode(":", base64_decode($this->credentials));
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
        $decoder = new JWTDecoder($key, Application::shared()->request->url->host);
        if (!($username = $decoder->decode($this->credentials)[JWTDataField])) {
            return null;
        }
        return new URLCredential($username);
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
            $context = $application->persistentContainer->viewContext;
            $fetchRequest = new FetchRequest();
            $fetchRequest->entity = EntityDescription::entity("User", $context);
            $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($username));
            return $context->fetch($fetchRequest)->first?->serialized($application->serialization);
        } catch (Exception) {
            return null;
        }
    }

    private function isValid(): bool
    {
        $application = Application::shared();
        $request = $application->request;
        if ($request->httpMethod === HTTPRequestMethod::options) {
            return true;
        }
        if ($application->session->valueForKey("user")) {
            return true;
        }
        if (!($credential = $this->credential)) {
            return false;
        }
        if (!($user = $this->user)) {
            return false;
        }
        /** @var string $password */
        $password = $user->valueForKey("password") ?? "";
        if ($this->scheme === AuthenticationScheme::basic) {
            if (is_password($password)) {
                return password_verify((string)$credential->password, $password);
            }
            return $credential->password === $password;
        }
        if ($this->scheme === AuthenticationScheme::digest) {
            $parameters = $this->parameters;
            if (!($username = $parameters["username"]) || !($uri = $parameters["uri"]) || !($nonce = $parameters["nonce"]) || !($nc = $parameters["nc"]) || !($cnonce = $parameters["cnonce"]) || !($qop = $parameters["qop"]) || ($parameters["algorithm"] !== "SHA-256")) {
                return false;
            }
            $HA1 = hash("sha256", "$username:{$request->url->host}:$password");
            $HA2 = hash("sha256", "$request->httpMethod:$uri");
            $response = hash("sha256", "$HA1:$nonce:$nc:$cnonce:$qop:$HA2");
            return $parameters["response"] === $response;
        }
        return $credential->user === $user->valueForKey("username");
    }
}