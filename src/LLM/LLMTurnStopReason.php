<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** Why a provider ended one turn, normalized across provider-specific wire formats. */
enum LLMTurnStopReason: string
{
    case completed = "completed";
    case toolUse = "toolUse";
    case outputLimit = "outputLimit";
    case refusal = "refusal";
}
