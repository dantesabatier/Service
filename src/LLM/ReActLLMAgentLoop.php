<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use Throwable;

/**
 * Implements the framework's tool-using ReAct decision cycle on top of `LLMAgentRuntime`.
 *
 * The loop decides when a provider turn concludes the run, which requested calls represent its
 * synthetic subagent operation, and how a child result is explained to the parent. Context
 * assembly, provider access, tool dispatch, caching, approvals, budgets, deadlines, and trace
 * boundaries remain in the runtime, so another loop inherits those guarantees without copying
 * this implementation.
 */
final class ReActLLMAgentLoop implements LLMAgentLoop
{
    private const string subagentToolName = "run_subagent";
    private const string subagentNoAnswer = "The subagent produced no answer.";
    private const string subagentTaskRequired = "Subagent task is required.";
    private const string subagentIncomplete = "The subagent ran out of iterations before finishing. Narrow the task and try again.";
    private const string subagentProviderFailure = "The subagent could not reach the model provider. Retrying may work.";
    private const string subagentProviderRefused = "The subagent could not reach the model provider, and the failure will repeat: it needs the server's configuration fixed, not another attempt. Report it rather than retrying.";
    private const string subagentToolProviderFailure = "The subagent could not reach its tool provider. Retrying may work.";
    private const string subagentToolProviderRefused = "The subagent's tool provider rejected or could not understand the request, and retrying it unchanged will repeat the failure.";
    private const string subagentDeadline = "The subagent ran out of time before finishing. Narrow the task and try again.";
    private const string subagentOutputLimit = "The subagent ran out of output tokens before finishing. Narrow the task and try again.";
    private const string subagentRefusal = "The subagent refused the task. Do not present its partial output as an answer.";
    private const string writeApprovalRequired = "This tool changes state and requires explicit user approval before it can run.";
    private const string toolCallLimit = "The shared tool-call budget is exhausted.";
    private const string subagentCallLimit = "The shared subagent budget is exhausted.";
    private const string inputTokenLimit = "The shared input-token budget is exhausted.";
    private const string outputTokenLimit = "The shared output-token budget is exhausted.";
    private const string totalTokenLimit = "The shared total-token budget is exhausted.";
    private const string contextLimit = "The assembled context exceeds its configured limit without a safe turn left to remove.";
    private const int subagentMaxIterations = 8;
    private ?ToolDescriptor $subagentToolDescriptor = null;

    private function subagentToolDescriptor(): ToolDescriptor
    {
        return $this->subagentToolDescriptor ??= new ToolDescriptor(
            self::subagentToolName,
            "Launch a focused subagent with the same tool catalogue to complete one bounded task. The subagent cannot launch further subagents.",
            [
                "type" => "object",
                "properties" => [
                    "task" => ["type" => "string", "description" => "The focused task the subagent should complete."],
                    "context" => ["type" => "string", "description" => "Optional context the subagent needs to complete the task."],
                ],
                "required" => ["task"],
                "additionalProperties" => false,
            ],
            "Run Subagent"
        );
    }

    /**
     * @throws Throwable
     */
    #[Override]
    public function run(LLMAgentRuntime $runtime): LLMRun
    {
        $tools = $runtime->canSpawnSubagents ? $runtime->tools->appending($this->subagentToolDescriptor()) : $runtime->tools;
        while (true) {
            $turn = $runtime->completeTurn($tools);
            $ending = match ($turn->stopReason) {
                LLMTurnStopReason::toolUse => null,
                LLMTurnStopReason::completed => [LLMRunStopReason::done, null],
                LLMTurnStopReason::outputLimit => [LLMRunStopReason::outputLimit, true],
                LLMTurnStopReason::refusal => [LLMRunStopReason::refusal, false],
            };
            if ($ending !== null) {
                [$stopReason, $isRetryable] = $ending;
                return $runtime->finish($stopReason, $isRetryable);
            }
            foreach ($turn->toolCalls as $toolCall) {
                $isSubagent = $runtime->canSpawnSubagents && $toolCall->name === self::subagentToolName;
                $runtime->executeTool($toolCall, $isSubagent, $isSubagent ? fn(): LLMAgentToolResult => $this->runSubagent($toolCall->arguments, $runtime) : null);
            }
        }
    }

    /**
     * @param Dictionary<mixed> $arguments
     * @param LLMAgentRuntime $runtime
     * @return LLMAgentToolResult
     * @throws Throwable
     */
    private function runSubagent(Dictionary $arguments, LLMAgentRuntime $runtime): LLMAgentToolResult
    {
        $task = trim((string)$arguments["task"]);
        if ($task === "") {
            return new LLMAgentToolResult(self::subagentTaskRequired, true);
        }
        $context = trim((string)$arguments["context"]);
        $content = $context === "" ? $task : "Task:\n$task\n\nContext:\n$context";
        $request = new LLMAgentRunRequest(new ArrayClass([new LLMMessage(LLMMessageRole::user, $content)]), $runtime->systemPrompt);
        $run = $runtime->child($request, self::subagentMaxIterations)->execute($this);
        $answer = $this->subagentResultText($run);
        return new LLMAgentToolResult($answer ?? $this->subagentFailureText($run), $answer === null, $this->propagatedStopReason($run->stopReason), $run->isRetryable);
    }

    private function subagentResultText(LLMRun $run): ?string
    {
        if (!$run->isComplete) {
            return null;
        }
        return $run->messages->last(fn(LLMMessage $message): bool => $message->role === LLMMessageRole::assistant && $message->content !== null && $message->content !== "")?->content;
    }

    private function subagentFailureText(LLMRun $run): string
    {
        return match ($run->stopReason) {
            LLMRunStopReason::providerFailure => $run->isRetryable === false ? self::subagentProviderRefused : self::subagentProviderFailure,
            LLMRunStopReason::toolProviderFailure => $run->isRetryable === false ? self::subagentToolProviderRefused : self::subagentToolProviderFailure,
            LLMRunStopReason::iterationCap => self::subagentIncomplete,
            LLMRunStopReason::deadline => self::subagentDeadline,
            LLMRunStopReason::outputLimit => self::subagentOutputLimit,
            LLMRunStopReason::refusal => self::subagentRefusal,
            LLMRunStopReason::toolCallLimit => self::toolCallLimit,
            LLMRunStopReason::subagentCallLimit => self::subagentCallLimit,
            LLMRunStopReason::inputTokenLimit => self::inputTokenLimit,
            LLMRunStopReason::outputTokenLimit => self::outputTokenLimit,
            LLMRunStopReason::totalTokenLimit => self::totalTokenLimit,
            LLMRunStopReason::writeApprovalRequired => self::writeApprovalRequired,
            LLMRunStopReason::contextLimit => self::contextLimit,
            LLMRunStopReason::done => self::subagentNoAnswer,
        };
    }

    private function propagatedStopReason(LLMRunStopReason $stopReason): ?LLMRunStopReason
    {
        return match ($stopReason) {
            LLMRunStopReason::toolCallLimit, LLMRunStopReason::subagentCallLimit, LLMRunStopReason::inputTokenLimit, LLMRunStopReason::outputTokenLimit, LLMRunStopReason::totalTokenLimit, LLMRunStopReason::writeApprovalRequired, LLMRunStopReason::contextLimit, LLMRunStopReason::deadline, LLMRunStopReason::toolProviderFailure => $stopReason,
            LLMRunStopReason::done, LLMRunStopReason::iterationCap, LLMRunStopReason::providerFailure, LLMRunStopReason::outputLimit, LLMRunStopReason::refusal => null,
        };
    }
}
