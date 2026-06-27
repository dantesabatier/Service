<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Sabatier\Foundation\InternalInconsistencyException;

/**
 * Thrown when the model calls a tool name that is not registered.
 *
 * It extends {@see InternalInconsistencyException} so {@see ToolRegistry::call} treats it as a
 * correctable LLM mistake — the message is fed back to the model rather than aborting the run.
 */
final class ToolNotFoundException extends InternalInconsistencyException
{
}
