<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\CompareOptions;
use function Sabatier\Foundation\string_begins_with;

/**
 * Rejects a token presented to the MCP endpoint that was not issued for it.
 *
 * A token says who the caller is; the audience says which resource it was meant for.
 * Without this check every valid token opens the MCP endpoint, so the credential a user
 * receives by signing in to the application also reaches MCP as a side effect — nothing
 * distinguishes it from one deliberately issued for that purpose.
 *
 * Scoped to the MCP endpoint, since that is the resource whose audience is configured;
 * other endpoints keep accepting tokens as before. It stays inert until
 * `MCP_TOKEN_AUDIENCE` is set, so the check can ship before the tokens that satisfy it
 * exist. Once set, it is required outright — an absent claim fails, and the tokens that
 * predate it stop reaching MCP. That is what turning it on is for.
 *
 * @internal
 */
final readonly class JSONWebTokenAudienceEvaluator implements AuthenticationAccessEvaluator
{
    private const string mcpPath = "/mcp";

    #[Override]
    public function evaluate(AccessEvaluationContext $context): bool
    {
        if (!string_begins_with($context->request->url->path, self::mcpPath, CompareOptions::caseInsensitive)) {
            return true;
        }
        if (!($audience = (string)($context->environment[MCPTokenAudienceKey] ?? ""))) {
            return true;
        }
        $authentication = $context->authentication;
        if (!($authentication instanceof BearerAuthentication)) {
            return true;
        }
        return $authentication->token?->payload?->audience === $audience;
    }
}
