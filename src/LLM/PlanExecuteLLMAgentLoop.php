<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use Throwable;

/**
 * Requires an explicitly ordered plan before allowing the model to execute real tools.
 *
 * A task the model can answer directly may still finish without a plan. Once it requests a real
 * tool, however, the loop rejects that call until the model submits at least one non-empty step
 * through its synthetic planning tool. The accepted plan remains in the ordinary conversation
 * as a tool call and result, so execution and final synthesis use the same guarded context,
 * budgets, deadline, and traces as every other strategy. The model may submit a revised plan later.
 */
final class PlanExecuteLLMAgentLoop implements LLMAgentLoop
{
    private const string planToolName = "submit_plan";
    private const string planRequired = "Submit an ordered plan before executing a real tool.";
    private const string planStepsRequired = "A plan requires at least one non-empty string step.";
    private ToolDescriptor $planToolDescriptor {
        get => $this->planToolDescriptor ??= new ToolDescriptor(
            self::planToolName,
            "Submit or revise the ordered plan for this task. Call this before any real tool, then execute the accepted steps and synthesize the final answer.",
            [
                "type" => "object",
                "properties" => [
                    "steps" => [
                        "type" => "array",
                        "description" => "Concrete ordered steps that lead to the requested result.",
                        "items" => ["type" => "string"],
                        "minItems" => 1,
                    ],
                ],
                "required" => ["steps"],
                "additionalProperties" => false,
            ],
            "Submit Plan"
        );
    }

    /** @throws Throwable */
    #[Override]
    public function run(LLMAgentSession $session): LLMAgentLoopOutcome
    {
        $syntheticTools = new ArrayClass([$this->planToolDescriptor]);
        $hasPlan = false;
        while (true) {
            $hadPlan = $hasPlan;
            $turn = $session->completeTurn($syntheticTools);
            if ($turn->stopReason === LLMTurnStopReason::completed) {
                return new LLMAgentLoopOutcome();
            }
            foreach ($turn->toolCalls as $toolCall) {
                if ($toolCall->name === self::planToolName) {
                    $steps = $this->planSteps($toolCall->arguments);
                    if ($steps === null) {
                        $session->rejectSyntheticToolCall($toolCall, self::planStepsRequired);
                        continue;
                    }
                    $hasPlan = true;
                    $session->completeSyntheticToolCall($toolCall, "Plan accepted with $steps->count steps. Execute it in order, revising it when new evidence requires a change.");
                    continue;
                }
                if (!$hadPlan) {
                    $session->rejectToolCall($toolCall, self::planRequired);
                    continue;
                }
                $session->executeTool($toolCall);
            }
        }
    }

    /** @return ArrayClass<string>|null */
    private function planSteps(Dictionary $arguments): ?ArrayClass
    {
        $steps = $arguments["steps"];
        if (!$steps instanceof ArrayClass || $steps->isEmpty) {
            return null;
        }
        $normalized = $steps->compactMap(function (mixed $step): ?string {
            if (!is_string($step) || trim($step) === "") {
                return null;
            }
            return trim($step);
        });
        return $normalized->count === $steps->count ? $normalized : null;
    }
}
