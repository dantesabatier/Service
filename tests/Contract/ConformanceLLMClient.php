<?php

declare(strict_types=1);

namespace Sabatier\Service\Testing;

use LogicException;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Service\LLM\LLMClient;
use Sabatier\Service\LLM\LLMExecutionDeadline;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMToolCall;
use Sabatier\Service\LLM\LLMTurn;
use Sabatier\Service\MCP\Response\ToolDescriptor;

/** A deterministic client fixture that adapts to direct, ReAct and explicit-plan conformance scenarios. */
final class ConformanceLLMClient extends LLMClient
{
    #[Override]
    public string $version {
        get => "test";
    }
    #[Override]
    public int $maxTokens {
        get => 1024;
    }

    private bool $submittedPlan = false;
    private bool $requestedTool = false;

    /**
     * @param bool $direct Whether the first turn completes without using a tool.
     * @param bool $repeatTool Whether every execution turn requests another tool.
     */
    public function __construct(private readonly bool $direct = false, private readonly bool $repeatTool = false)
    {
        parent::__construct();
    }

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null, ?LLMExecutionDeadline $deadline = null): LLMTurn
    {
        if ($this->direct) {
            return new LLMTurn("completed", new ArrayClass(), 5, 3);
        }
        if (!$this->submittedPlan && $tools->contains(fn(ToolDescriptor $tool): bool => $tool->name === "submit_plan")) {
            $this->submittedPlan = true;
            $arguments = Dictionary::dictionaryWithArray(["steps" => ["Inspect the evidence", "Synthesize the result"]]);
            return new LLMTurn(null, new ArrayClass([new LLMToolCall("plan-1", "submit_plan", $arguments)]), 5, 3);
        }
        if (!$this->requestedTool || $this->repeatTool) {
            $this->requestedTool = true;
            $messageCount = $messages->count;
            return new LLMTurn(null, new ArrayClass([new LLMToolCall("tool-$messageCount", "external_tool", new Dictionary())]), 5, 3);
        }
        return new LLMTurn("completed", new ArrayClass(), 5, 3);
    }

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    protected function buildRequest(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): URLRequest
    {
        throw new LogicException("Conformance client does not build requests.");
    }

    /** @param Dictionary<mixed> $body */
    #[Override]
    protected function parse(Dictionary $body): LLMTurn
    {
        throw new LogicException("Conformance client does not parse responses.");
    }
}
