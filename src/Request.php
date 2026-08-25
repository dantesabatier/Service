<?php

declare(strict_types=1);

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
 * specific to the Service layer, including
 * - Parsed request body from URL query or HTTP body
 * - JSON serialization directives from the `Serialization` header
 * - Authorization header parsing
 * - The remote address of the connection
 * - Detection of CORS preflight requests
 *
 * This class centralizes request-related data and
 * provides a convenient API for responders and services
 * to access request content and metadata.
 */
final class Request extends URLRequest
{
    /** @var Dictionary<string> Query string parameters parsed from the request URL. */
    private(set) Dictionary $queryParameters {
        get => $this->queryParameters ??= new URLComponents($this->url->absoluteString)->queryItems?->reduce(new Dictionary(),
            /**
             * @param Dictionary<string> $result
             * @param URLQueryItem $queryItem
             * @return Dictionary<string>
             */
            function (Dictionary $result, URLQueryItem $queryItem): Dictionary {
                $result[$queryItem->name] = $queryItem->value;
                return $result;
            }) ?? new Dictionary();
    }
    /** @var Dictionary<mixed> Request input parameters, taking the parsed body as authoritative and drawing on the query string only for keys the body does not provide. */
    private(set) Dictionary $parameters {
        get => $this->parameters ??= $this->queryParameters->merging(Dictionary::dictionaryWithArray($this->getParsedBody()));
    }
    private bool $isSerializationResolved = false;
    /** @var Dictionary<mixed>|null Describes which attributes/relationships to include when serializing objects for this request. */
    private(set) ?Dictionary $serialization {
        get {
            if ($this->isSerializationResolved) {
                return $this->serialization;
            }
            $this->isSerializationResolved = true;
            if (($string = $this->valueForHttpHeaderField("Serialization")) && json_validate($string) && ($array = json_decode($string, true))) {
                return $this->serialization = Dictionary::dictionaryWithArray($array);
            }
            return $this->serialization = null;
        }
    }
    /** @var AuthorizationHeader Parsed `Authorization` header. */
    private(set) AuthorizationHeader $authorizationHeader {
        get => $this->authorizationHeader ??= new AuthorizationHeader($this->valueForHttpHeaderField("Authorization") ?? "");
    }
    /** @var string|null The TCP peer address of the connection, or null when unavailable. */
    private(set) ?string $remoteAddress {
        get => $this->remoteAddress ??= $_SERVER["REMOTE_ADDR"] ?? null;
    }
    /** @var bool Returns true if this is a CORS preflight request. */
    public bool $isPreflight {
        get => $this->httpMethod === HTTPRequestMethod::options;
    }

    public function __construct()
    {
        parent::__construct(new URL(request_url()));
        $this->allHTTPHeaderFields = new Dictionary(getallheaders());
        $requestMethod = $_SERVER["REQUEST_METHOD"] ?? HTTPRequestMethod::get;
        $override = strtoupper((string)$this->valueForHttpHeaderField("X-Http-Method-Override"));
        $this->httpMethod = $requestMethod === HTTPRequestMethod::post && match ($override) {
            HTTPRequestMethod::put, HTTPRequestMethod::patch, HTTPRequestMethod::delete => true,
            default => false,
        } ? $override : $requestMethod;
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
