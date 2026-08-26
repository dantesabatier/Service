<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\Networking\URLResponse;
use Sabatier\Foundation\Networking\URLSession;
use Sabatier\Foundation\Networking\URLSessionConfiguration;
use Sabatier\Foundation\URL;
use Sabatier\Service\InternalServerErrorException;
use Sabatier\Service\MCP\Response\ToolDescriptor;

/**
 * Abstract base for LLM provider integrations.
 *
 * Handles transport via URLSession. Subclasses own request serialization (`buildRequest`) and
 * response deserialization (`parse`), covering provider-specific wire formats (headers, message
 * structure, tool schemas, token fields). The public surface is `complete`, which executes one
 * request–response cycle and returns an `LLMTurn`.
 */
abstract class LLMClient
{
    /** @var string API version header value sent with every request. */
    abstract public string $version {
        get;
    }
    /** @var int Maximum output tokens to request from the model. */
    abstract public int $maxTokens {
        get;
    }
    /** @var float|int Request timeout, in seconds. Local models can take considerably longer than the shared session's default to produce a first token, so the default is generous. Override per provider. */
    public float|int $timeoutIntervalForRequest = 300.0;

    /** @var int Times a transient failure is retried before the request is given up on. Zero disables retrying. */
    public int $maximumRetryCount = 3;
    /** @var float Seconds to wait before the first retry; each further attempt doubles it. */
    public float $initialRetryDelay = 1.0;
    /** @var float Ceiling for any single wait between attempts, applied to the exponential delay and to a provider's `Retry-After` alike, so one oversized value cannot stall the run for the whole request timeout. */
    public float $maximumRetryDelay = 30.0;

    /** @var Dictionary<mixed> Per-provider request-body fields merged over the built body; opaque keys the client neither interprets nor validates, overriding its own values on collision. */
    public Dictionary $extraBody {
        get => $this->extraBody ??= new Dictionary();
    }

    /** @var URLSession The session used to send requests, configured with {@see LLMClient::$timeoutIntervalForRequest} rather than reusing {@see URLSession::shared()}, whose default timeout is too short for local models. */
    private URLSession $session {
        get => $this->session ??= new URLSession(clone(URLSessionConfiguration::default(), [
            "timeoutIntervalForRequest" => $this->timeoutIntervalForRequest,
        ]));
    }

    /**
     * @param string|null $model The model name to request, or `null` to use the provider's default.
     * @param URL|null $endpoint The provider API endpoint, or `null` to use the provider's default.
     * @param string|null $key The provider API key, or `null` when unconfigured.
     */
    public function __construct(public readonly ?string $model = null, public readonly ?URL $endpoint = null, public readonly ?string $key = null)
    {
    }

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    abstract protected function buildRequest(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): URLRequest;

    /**
     * @param Dictionary<mixed> $body
     * @return LLMTurn
     */
    abstract protected function parse(Dictionary $body): LLMTurn;

    /**
     * Sends one request to the LLM and returns the resulting turn.
     *
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): LLMTurn
    {
        $request = $this->buildRequest($messages, $tools, $systemPrompt);
        $body = $this->send($request);
        return $this->parse($body);
    }

    /**
     * Sends one request, retrying the failures that are worth retrying, and returns the decoded body.
     *
     * A provider failure must not reach `parse` as an empty body: a turn parsed from `[]` carries no text and no tool calls, which is exactly the shape of a model that decided to stop, so the run would be reported as complete when nothing answered it. Every failure therefore throws.
     *
     * @throws InternalServerErrorException The provider failed in a way retrying cannot fix, or every attempt was exhausted.
     * @throws InternalInconsistencyException The transport itself failed.
     */
    protected function send(URLRequest $request): Dictionary
    {
        $attempt = 0;
        while (true) {
            $data = null;
            $error = null;
            $response = null;
            $this->session->dataTaskWithRequest($request, function (?string $responseData, ?URLResponse $urlResponse, ?Error $err) use (&$data, &$error, &$response): void {
                $data = $responseData;
                $error = $err;
                $response = $urlResponse;
            })->resume();
            $statusCode = $response instanceof HTTPURLResponse ? $response->statusCode : null;
            if (!($error instanceof Error) && $statusCode !== null && !self::isRetryable($statusCode)) {
                $statusCode >= HTTPStatusCode::badRequest ? throw new InternalServerErrorException(self::failureReason($statusCode, $data)) : null;
                return Dictionary::dictionaryWithArray(json_decode($data ?? "[]") ?? [], false);
            }
            if ($attempt >= $this->maximumRetryCount) {
                $error instanceof Error ? throw new InternalInconsistencyException(error: $error) : throw new InternalServerErrorException(self::failureReason($statusCode, $data));
            }
            usleep((int)round($this->retryDelay($attempt++, $response) * 1_000_000.0));
        }
    }

    /**
     * Renders a tool result for a provider whose wire format has no failure flag of its own.
     *
     * Anthropic carries the distinction natively (`is_error`), so a failed result stays recognisable there. The OpenAI and Ollama tool messages have no such field, and without a marker in the text a failure reaches the model looking exactly like a successful result — so it treats the error message as the answer instead of correcting the call. The prefix restores what the format drops.
     *
     * @param bool $isError Whether the tool call this message reports failed.
     */
    protected static function toolResultText(?string $content, bool $isError): string
    {
        $text = $content ?? "";
        return $isError ? "Error: $text" : $text;
    }

    /**
     * Whether a status is worth sending the same request again for.
     *
     * Rate limiting and the 5xx family are transient by definition. A 4xx the caller caused — a bad key, an unknown model, a malformed body — returns the same answer however many times it is asked, so retrying it only delays the error. `requestTimeout` is the one 4xx that is about timing rather than the request's content.
     */
    private static function isRetryable(int $statusCode): bool
    {
        return $statusCode === HTTPStatusCode::tooManyRequests || $statusCode === HTTPStatusCode::requestTimeout || $statusCode >= HTTPStatusCode::internalServerError;
    }

    /** Describes a failed response in terms the caller can act on, preferring the provider's own words when it sent any. */
    private static function failureReason(?int $statusCode, ?string $data): string
    {
        $body = trim((string)$data);
        $reason = $statusCode === null ? "The LLM provider returned no response" : sprintf("The LLM provider returned HTTP %d", $statusCode);
        return $body === "" ? $reason : "$reason: $body";
    }

    /**
     * Seconds to wait before the next attempt: the provider's own `Retry-After` when it sent one, and otherwise an exponentially growing delay.
     *
     * `Retry-After` is authoritative because the provider knows when its own limit resets; guessing shorter earns another 429 and guessing longer wastes the caller's time. The header comes as either a delay in seconds or an HTTP date, and only the numeric form is honoured here — a date needs a clock comparison that would make the wait depend on the skew between the two machines.
     */
    private function retryDelay(int $attempt, ?URLResponse $response): float
    {
        $retryAfter = $response instanceof HTTPURLResponse ? trim((string)$response->allHeaderFields["Retry-After"]) : "";
        if (ctype_digit($retryAfter)) {
            return min((float)$retryAfter, $this->maximumRetryDelay);
        }
        return min($this->initialRetryDelay * (float)(2 ** $attempt), $this->maximumRetryDelay);
    }
}
