<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** How the loop resolved a tool call requested by the model. */
enum LLMToolCallDisposition: string
{
    case executed = "executed";
    case cached = "cached";
    case denied = "denied";
    case budgetExceeded = "budgetExceeded";
    case deadlineExceeded = "deadlineExceeded";
    case failed = "failed";
}
