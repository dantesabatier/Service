<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\URLQueryItem;
use function Sabatier\Foundation\getallheaders;
use function Sabatier\Foundation\request_url;

class Request extends URLRequest
{
    private(set) Dictionary $parsedBody {
        get {
            if (!isset($this->parsedBody)) {
                $this->parsedBody = Dictionary::dictionaryWithArray($this->getParsedBody());
                if ($this->parsedBody->isEmpty) {
                    $components = new URLComponents($this->url->absoluteString);
                    if ($dictionary = $components->queryItems?->reduce(new Dictionary(), function (Dictionary $result, URLQueryItem $queryItem): Dictionary {
                        $result[$queryItem->name] = $queryItem->value;
                        return $result;
                    })) {
                        $this->parsedBody->merge($dictionary);
                    }
                }
            }
            return $this->parsedBody;
        }
    }
    /** @var Dictionary<mixed>|null */
    private(set) ?Dictionary $serialization {
        get {
            if (!isset($this->serialization)) {
                if (($string = $this->valueForHttpHeaderField("Serialization")) && json_validate($string) && ($array = json_decode($string, true))) {
                    $this->serialization = Dictionary::dictionaryWithArray($array);
                }
                $this->serialization ??= null;
            }
            return $this->serialization;
        }
    }
    private(set) AuthParameter $authParameter {
        get {
            if (!isset($this->authParameter)) {
                $parameter = $this->valueForHttpHeaderField("Authorization") ?? "";
                $components = explode(" ", $parameter, 2);
                if (count($components) !== 2) {
                    $components = [AuthenticationScheme::basic->value, ""];
                }
                [$name, $value] = $components;
                $this->authParameter = new AuthParameter($name, $value);
            }
            return $this->authParameter;
        }
    }

    public function __construct()
    {
        parent::__construct(new URL(request_url()));
        $this->allHTTPHeaderFields = new Dictionary(getallheaders());
        $this->httpMethod = $this->valueForHttpHeaderField("X-Http-Method-Override") ?? $_SERVER["REQUEST_METHOD"] ?? HTTPRequestMethod::get;
        $this->httpBody = match ($this->httpMethod) {
            HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::delete, HTTPRequestMethod::patch => (function (): ?string {
                $contentType = $this->valueForHttpHeaderField("Content-Type") ?? "text/plain";
                $mediaType = $contentType;
                if (str_contains($contentType, ";")) {
                    [$mediaType,] = explode(";", $contentType);
                }
                $httpBody = match ($mediaType) {
                    "application/x-www-form-urlencoded", "application/json" => file_get_contents("php://input"),
                    default => null
                };
                return empty($httpBody) ? null : $httpBody;
            })(),
            default => null
        };
    }
}
