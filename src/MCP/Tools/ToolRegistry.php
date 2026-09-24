<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Service\Application;
use Sabatier\Service\DefaultAccessPolicy;
use Sabatier\Service\ForbiddenException;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use Sabatier\Service\RequestSecurityContext;
use Throwable;

/**
 * Holds the resolved MCP tools and dispatches calls by name.
 *
 * Exposes the tool catalogue as `$list` (`ArrayClass<ToolDescriptor>`) for the LLM
 * client, and dispatches `call()` to the matching tool implementation.
 */
final class ToolRegistry
{
    /** @var Dictionary<AbstractTool> */
    private Dictionary $tools {
        get => $this->tools ??= $this->toolList->reduce(new Dictionary(),
            /**
             * @param Dictionary<AbstractTool> $carry
             * @param AbstractTool $tool
             * @return Dictionary<AbstractTool>
             */
            function (Dictionary $carry, AbstractTool $tool) {
                $carry[$tool->name] = $tool;
                return $carry;
            });
    }
    /** @var ArrayClass<ToolDescriptor> Descriptors advertised to the model in registry order. */
    public ArrayClass $list {
        get => $this->list ??= $this->tools->map(fn(AbstractTool $tool) => new ToolDescriptor($tool->name, $tool->description, $tool->inputSchema, $tool->title, [
            "readOnlyHint" => $tool->isReadOnly,
            "destructiveHint" => $tool->isDestructive,
            "idempotentHint" => $tool->isIdempotent,
            "openWorldHint" => $tool->isOpenWorld,
        ]));
    }

    /** @param ArrayClass<AbstractTool> $toolList The resolved tool implementations to register by name. */
    public function __construct(private readonly ArrayClass $toolList)
    {
    }

    /**
     * Whether a repeated call to the named tool may be served from a cache instead of executed again.
     *
     * An unknown name answers `false`: the registry cannot vouch for a tool it does not hold, and treating it as cacheable would suppress a call it never inspected.
     *
     * @param string $name The name of the tool to test.
     */
    public function isCacheable(string $name): bool
    {
        return $this->tools[$name]?->isCacheable ?? false;
    }

    /**
     * Whether the registry contains a tool with the supplied name.
     *
     * @param string $name The name to look up.
     */
    public function isRegistered(string $name): bool
    {
        return $this->tools[$name] instanceof AbstractTool;
    }

    /**
     * Whether the named concrete invocation only reads state.
     *
     * @param string $name The registered tool name.
     * @param Dictionary<mixed> $arguments The arguments selecting the concrete operation.
     */
    public function isReadOnlyCall(string $name, Dictionary $arguments): bool
    {
        return $this->tools[$name]?->isReadOnlyCall($arguments) ?? false;
    }

    /**
     * Invokes a tool by name and never lets it throw past this point.
     *
     * This is the single funnel both MCP and the in-process agent loop converge on. A tool that
     * mis-fires on the LLM's bad input throws an `InternalInconsistencyException` (via `fatal_error`)
     * carrying a message written for the model; that is captured and returned as a failed
     * `ToolResult` so the caller can feed it back for correction, and logged once here so the
     * programmer also learns the LLM is misbehaving. Authorization denials (`ForbiddenException`)
     * get a retry-stopper appended here — the guidance is for the model, so it belongs to this
     * funnel, not to the security layer that raised the denial. Any other `Throwable` is a real
     * program fault, not something the model can fix, and propagates to the caller.
     *
     * The reason is read off the attached `Error` rather than `getMessage()`. Core Data reports a
     * validation failure by throwing `new InternalInconsistencyException(error: $error)` with no
     * message at all, so reading the message alone hands the model an empty failure — it is told
     * the call did not work and never which constraint it broke, which is the one thing that would
     * let it fix the call. `ErrorResponder` already reports HTTP errors from the same `Error`.
     *
     * @param string $name The name of the tool to invoke.
     * @param Dictionary<mixed> $arguments The arguments supplied by the model for the call.
     * @return ToolResult The tool's content on success, or a correctable failure carrying the message for the model.
     * @throws Throwable A fatal program fault (anything other than an `InternalInconsistencyException`) raised by the tool.
     */
    public function call(string $name, Dictionary $arguments): ToolResult
    {
        try {
            /** @var AbstractTool $tool */
            $tool = $this->tools[$name] ?? throw new ToolNotFoundException("Unknown tool: $name");
            $complaint = $this->schemaComplaint($tool, $arguments);
            if ($complaint !== null) {
                return ToolResult::failure($complaint);
            }
            $this->authorize($tool, $arguments);
            $context = RequestSecurityContext::current();
            return ToolResult::success($context === null ? $tool->execute($arguments) : RequestSecurityContext::perform($context->enforcingWrites(), fn(): ArrayClass => $tool->execute($arguments)));
        } catch (InternalInconsistencyException $exception) {
            error_log((string)$exception);
            $reason = $this->reason($exception);
            return ToolResult::failure($exception instanceof ForbiddenException ? trim($reason . " " . "Do not retry this call.") : $reason);
        }
    }

    /**
     * What is wrong with the arguments before the tool is asked to run, or `null` when nothing is.
     *
     * A key the schema does not declare is the failure worth catching: the tool reads the ones it knows and ignores the rest, so `filter` where `predicate` was meant produces a fetch with no filter at all — every row, returned as though it were the answer, with nothing to suggest the call was misread. Naming the unknown key and listing the accepted ones is what lets the model fix it; silence is what makes it believe the result.
     *
     * A missing required key is checked in the same pass. The intent is to catch the model's own mistakes, not to police the schema: a tool whose schema declares no properties accepts anything, and a value's type is left to the tool, which reports a type it cannot use in terms of its own domain.
     *
     * @param AbstractTool $tool The tool the call is bound for.
     * @param Dictionary<mixed> $arguments The arguments supplied by the model.
     */
    private function schemaComplaint(AbstractTool $tool, Dictionary $arguments): ?string
    {
        $schema = $tool->inputSchema;
        $properties = $schema["properties"] ?? null;
        if (!is_array($properties) || $properties === []) {
            return null;
        }
        /** @var ArrayClass<string> $unknown */
        $unknown = $arguments->keys->compactMap(fn(string $key): ?string => !array_key_exists($key, $properties) ? $key : null);
        if (!$unknown->isEmpty) {
            $accepted = implode(", ", array_keys($properties));
            return sprintf("%s does not accept %s. Accepted arguments: %s. Re-read the tool's schema and call it again.", $tool->name, $unknown->join(", "), $accepted);
        }
        /** @var list<string> $required */
        $required = is_array($schema["required"] ?? null) ? $schema["required"] : [];
        /** @var ArrayClass<string> $missing */
        $missing = new ArrayClass($required)->filter(fn(string $key): bool => !$arguments->offsetExists($key));
        return $missing->isEmpty ? null : sprintf("%s requires %s. Supply it and call again.", $tool->name, $missing->join(", "));
    }

    /**
     * @param AbstractTool $tool
     * @param Dictionary<mixed> $arguments
     * @throws ForbiddenException
     * @throws Exception
     */
    private function authorize(AbstractTool $tool, Dictionary $arguments): void
    {
        $context = RequestSecurityContext::current();
        if ($context === null) {
            !Application::shared()->accessPolicy instanceof DefaultAccessPolicy ?: throw new ForbiddenException("No security context is in effect, so tools cannot be called.");
            return;
        }
        if (!$context->isSecurityEnabled) {
            return;
        }
        $declaration = $tool->authorizationRequirements($arguments) ?? throw new ForbiddenException(sprintf("%s does not declare what it must be authorized for, so it cannot be called.", $tool->name));
        if ($declaration->isNone) {
            return;
        }
        !$declaration->requirements->isEmpty ?: throw new ForbiddenException(sprintf("%s does not declare what it must be authorized for, so it cannot be called.", $tool->name));
        $user = $context->user ?? throw new ForbiddenException("You must be authenticated to perform this action.");
        foreach ($declaration->requirements as $requirement) {
            Application::shared()->authorizationService->isAuthorized($user, $requirement->resource, $requirement->action, $context->scopes, $context->managedObjectContext) ?: throw new ForbiddenException(sprintf("You don't have permission to %s \"%s\".", $requirement->action->name, $requirement->resource));
        }
    }

    private function reason(InternalInconsistencyException $exception): string
    {
        $error = $exception->error;
        return $exception->getMessage() ?: ($error->localizedFailureReason ?? $error->localizedDescription);
    }
}
