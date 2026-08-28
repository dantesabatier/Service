<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use RuntimeException;

/** Signals that an operation could not return a usable result before its agent deadline. */
final class LLMDeadlineExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("The agent execution deadline has expired.");
    }
}
