<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** Why an agentic run stopped, distinguishing the endings a caller must react to differently: the model concluding, the loop running out of iterations, the run running out of time, and the provider failing. */
enum LLMRunStopReason: string
{
    case done = "done";
    case iterationCap = "iterationCap";
    case deadline = "deadline";
    case providerFailure = "providerFailure";
}
