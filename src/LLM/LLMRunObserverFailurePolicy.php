<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** Decides whether a broken telemetry sink may stop the agentic run it observes. */
enum LLMRunObserverFailurePolicy: string
{
    case bestEffort = "bestEffort";
    case strict = "strict";
}
