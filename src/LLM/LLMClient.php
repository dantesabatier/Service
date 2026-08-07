<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\Networking\URLResponse;
use Sabatier\Foundation\Networking\URLSession;
use Sabatier\Foundation\Networking\URLSessionConfiguration;
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
    /** API version header value sent with every request. */
    abstract public string $version {
        get;
    }
    /** Maximum output tokens to request from the model. */
    abstract public int $maxTokens {
        get;
    }
    /**
     * Request timeout, in seconds. Local models can take considerably longer than the shared
     * session's default to produce a first token, so the default is generous. Override per provider.
     */
    public float|int $timeoutIntervalForRequest = 300.0;

    /**
     * Extra request-body fields merged over the ones the client builds, for per-provider generation
     * settings the base body does not model. Keys are opaque: the client neither interprets nor
     * validates them, so any field the backend understands works (a sampling parameter at the root,
     * or a nested object its API expects). Empty by default, so a provider that sets nothing is
     * unaffected. A key here overrides the client's own value for that field.
     *
     * @var Dictionary<mixed>
     */
    public Dictionary $extraBody {
        get => $this->extraBody ??= new Dictionary();
    }

    /**
     * The session used to send requests. Configured with {@see LLMClient::$timeoutIntervalForRequest}
     * rather than reusing {@see URLSession::shared()}, whose default timeout is too short for local models.
     */
    private URLSession $session {
        get => $this->session ??= new URLSession(clone(URLSessionConfiguration::default(), [
            "timeoutIntervalForRequest" => $this->timeoutIntervalForRequest,
        ]));
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
     * @throws InternalServerErrorException
     */
    protected function send(URLRequest $request): Dictionary
    {
        $data = null;
        $error = null;
        $this->session->dataTaskWithRequest($request, function (?string $responseData, ?URLResponse $response, ?Error $err) use (&$data, &$error): void {
            $data = $responseData;
            $error = $err;
        })->resume();
        !$error instanceof Error ?: throw new InternalInconsistencyException(error: $error);
        return Dictionary::dictionaryWithArray(json_decode($data ?? "[]", true) ?? [], false);
    }
}
