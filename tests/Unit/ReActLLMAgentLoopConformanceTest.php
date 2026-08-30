<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Sabatier\Service\LLM\LLMAgentLoop;
use Sabatier\Service\LLM\ReActLLMAgentLoop;
use Sabatier\Service\Testing\LLMAgentLoopConformanceTestCase;

final class ReActLLMAgentLoopConformanceTest extends LLMAgentLoopConformanceTestCase
{
    protected function loop(): LLMAgentLoop
    {
        return new ReActLLMAgentLoop();
    }
}
