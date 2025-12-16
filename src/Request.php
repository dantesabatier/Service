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

/**
 * Represents an HTTP service request.
 *
 * Extends URLRequest to provide additional functionality
 * specific to the Service layer, including:
 * - Parsed request body from URL query or HTTP body
 * - JSON serialization directives from the `Serialization` header
 * - Authorization header parsing
 * - Detection of CORS preflight requests
 *
 * This class centralizes request-related data and
 * provides a convenient API for responders and services
 * to access request content and metadata.
 */
class Request extends URLRequest
{
    /** @var Dictionary Parsed body of the request. */
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
    /** @var Dictionary|null Describes which attributes/relationships to include when serializing objects for this request. */
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
    /** @var AuthorizationHeader Parsed `Authorization` header. */
    private(set) AuthorizationHeader $authorizationHeader {
        get => $this->authorizationHeader ??= new AuthorizationHeader($this->valueForHttpHeaderField("Authorization") ?? "");
    }
    /** @var bool Returns true if this is a CORS preflight request. */
    public bool $isPreflight {
        get => $this->httpMethod === HTTPRequestMethod::options;
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
