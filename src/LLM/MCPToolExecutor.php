<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Closure;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\MCP\MCPClient;
use Sabatier\Service\MCP\MCPClientException;
use Sabatier\Service\MCP\Response\ToolDescriptor;

/** Adapts a stateful remote MCP client into the agent's tool catalogue and execution boundary. */
final class MCPToolExecutor implements LLMToolExecutor
{
    /** @var ArrayClass<ToolDescriptor> The remote catalogue advertised to the model. */
    #[Override]
    public ArrayClass $tools {
        get {
            try {
                return $this->client->tools;
            } catch (MCPClientException $exception) {
                throw new LLMToolProviderException($exception->getMessage(), $exception->isTransient, $exception);
            }
        }
    }

    /**
     * Remote tools are treated as state-changing and non-cacheable unless the application supplies trusted classifiers. Descriptions and JSON Schema constrain arguments; they do not authorize effects.
     *
     * @param MCPClient $client The initialized-on-demand remote MCP session.
     * @param Closure(LLMToolCall): bool|null $readOnly Returns true only for concrete calls known not to change state. Null classifies every call as state-changing.
     * @param Closure(LLMToolCall): bool|null $cacheable Returns true only for concrete read calls safe to reuse during one run. Null disables caching.
     */
    public function __construct(private readonly MCPClient $client, private readonly ?Closure $readOnly = null, private readonly ?Closure $cacheable = null)
    {
    }

    #[Override]
    public function contains(LLMToolCall $call): bool
    {
        return $this->tools->contains(fn(ToolDescriptor $tool): bool => $tool->name === $call->name);
    }

    #[Override]
    public function isReadOnly(LLMToolCall $call): bool
    {
        return $this->contains($call) && $this->readOnly !== null && ($this->readOnly)($call) === true;
    }

    #[Override]
    public function isCacheable(LLMToolCall $call): bool
    {
        return $this->isReadOnly($call) && $this->cacheable !== null && ($this->cacheable)($call) === true;
    }

    #[Override]
    public function execute(LLMToolCall $call, ?LLMExecutionDeadline $deadline = null): LLMToolExecutionResult
    {
        $deadline?->enforce();
        try {
            $result = $this->client->callTool($call->name, $call->arguments, $deadline?->remainingTime);
        } catch (MCPClientException $exception) {
            $deadline?->enforce();
            throw new LLMToolProviderException($exception->getMessage(), $exception->isTransient, $exception);
        }
        $deadline?->enforce();
        return new LLMToolExecutionResult($result->text, $result->isError);
    }
}
