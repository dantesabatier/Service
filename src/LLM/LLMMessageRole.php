<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** Identifies the participant role of a message in an LLM conversation. */
enum LLMMessageRole: string
{
    case system = "system";
    case user = "user";
    case assistant = "assistant";
    case tool = "tool";
}
