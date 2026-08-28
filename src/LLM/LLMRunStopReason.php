<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** Why an agentic run stopped, distinguishing the endings a caller must react to differently. */
enum LLMRunStopReason: string
{
    case done = "done";
    case iterationCap = "iterationCap";
    case deadline = "deadline";
    case providerFailure = "providerFailure";
    case toolProviderFailure = "toolProviderFailure";
    case outputLimit = "outputLimit";
    case refusal = "refusal";
    case toolCallLimit = "toolCallLimit";
    case subagentCallLimit = "subagentCallLimit";
    case inputTokenLimit = "inputTokenLimit";
    case outputTokenLimit = "outputTokenLimit";
    case totalTokenLimit = "totalTokenLimit";
    case writeApprovalRequired = "writeApprovalRequired";
    case contextLimit = "contextLimit";
}
