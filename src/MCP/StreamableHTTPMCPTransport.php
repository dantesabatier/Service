<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use InvalidArgumentException;
use JsonException;
use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\Networking\URLResponse;
use Sabatier\Foundation\Networking\URLSession;
use Sabatier\Foundation\Networking\URLSessionConfiguration;
use Sabatier\Foundation\URL;

/** Sends MCP messages through the Streamable HTTP transport without retrying state-changing calls. */
final readonly class StreamableHTTPMCPTransport implements MCPTransport
{
    /** @var Dictionary<string> */
    private Dictionary $additionalHeaders;

    /**
     * @param URL $endpoint The remote MCP endpoint.
     * @param Dictionary<string> $additionalHeaders Authentication and application headers added to every request.
     * @param float $timeoutInterval Default request timeout in seconds.
     */
    public function __construct(private URL $endpoint, Dictionary $additionalHeaders = new Dictionary(), private float $timeoutInterval = 30.0)
    {
        if ($timeoutInterval <= 0.0) {
            throw new InvalidArgumentException("timeoutInterval must be greater than zero.");
        }
        $this->additionalHeaders = clone $additionalHeaders;
    }

    #[Override]
    public function send(Dictionary $message, Dictionary $headers, ?float $timeout = null): MCPClientResponse
    {
        $effectiveTimeout = $timeout === null ? $this->timeoutInterval : max(1.0, min($this->timeoutInterval, $timeout));
        $request = new URLRequest($this->endpoint, timeoutInterval: $effectiveTimeout);
        $request->httpMethod = HTTPRequestMethod::post;
        $request->allHTTPHeaderFields = clone $this->additionalHeaders;
        $request->setValueForHttpHeaderField("application/json", "Accept");
        $request->setValueForHttpHeaderField("application/json", "Content-Type");
        $headers->forEach(fn(string $value, string $field) => $request->setValueForHttpHeaderField($value, $field));
        try {
            $request->httpBody = (string)json_encode($message, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new MCPClientException("The MCP message could not be encoded: {$exception->getMessage()}", false, $exception);
        }
        /** @psalm-var URLSessionConfiguration $configuration */
        $configuration = clone(URLSessionConfiguration::default(), ["timeoutIntervalForRequest" => $effectiveTimeout]);
        $data = null;
        $error = null;
        $response = null;
        new URLSession($configuration)->dataTaskWithRequest($request, function (?string $responseData, ?URLResponse $urlResponse, ?Error $err) use (&$data, &$error, &$response): void {
            $data = $responseData;
            $error = $err;
            $response = $urlResponse;
        })->resume();
        if ($error instanceof Error) {
            throw new MCPClientException((string)$error->localizedFailureReason ?: "The MCP transport failed without a reason.", true, transportError: $error);
        }
        if (!$response instanceof HTTPURLResponse) {
            throw new MCPClientException("The MCP transport returned no HTTP response.", true);
        }
        return new MCPClientResponse($response->statusCode, $response->allHeaderFields, (string)$data);
    }
}
