<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\Jobs\JobRegistry;
use Sabatier\Service\Jobs\JobResolver;
use Sabatier\Service\MCP\Response\ContentItem;
use function Sabatier\Foundation\fatal_error;

/**
 * Runs a named domain job in the current request.
 *
 * The agent-facing surface over the same job catalogue the CLI entry point
 * (`JobRunner`) matches its command-line argument against: it resolves the
 * name against the discovered `Job` classes, runs it against the request
 * context, and persists its changes — the same transaction boundary the CRUD
 * tools keep, `save` here because this tool, not the responder, owns the job's
 * writes. The transaction author is stamped with the authenticated user so
 * persistent history attributes the run to the agent's caller.
 *
 * Authorization is enforced per call since the MCP request URL never names the
 * resource: the caller needs a permission on the `Jobs` resource. Running a job
 * is a coarse action that may read or write, so the gate is `AuthorizationType::any`
 * — seed a `Jobs` permission of type `any` on the roles allowed to invoke jobs.
 *
 * A job that throws propagates its fault to the registry funnel, which lets a
 * real program fault abort the run; an unknown job name is an
 * `InternalInconsistencyException` and comes back as a correctable failure for
 * the model.
 */
final class JobTool extends AbstractTool
{
    /**
     * @var string The RBAC resource name gated per call. Since the MCP request URL is always
     * `/mcp` and never names a resource, this is the identifier the caller must hold a
     * permission on to run any job — seed a `Jobs` permission of type `any` on the roles
     * allowed to invoke jobs. It is a coarse gate over the whole job catalogue, not any one
     * entity, so it is a literal constant rather than a per-request entity name.
     */
    private const string jobsResource = "Jobs";
    private JobRegistry $registry {
        get => $this->registry ??= new JobRegistry(new JobResolver()->resolve());
    }
    #[Override]
    public string $name {
        get => "run_job";
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => [
                "job" => ["type" => "string", "description" => "Name of the job to run. Matches the job's class short name unless the job overrides its `name` hook."],
            ],
            "required" => ["job"],
        ];
    }

    #[Override]
    public function authorizationRequirements(Dictionary $arguments): ?AuthorizationRequirements
    {
        return AuthorizationRequirements::one(self::jobsResource, AuthorizationType::any);
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        /** @var string $name */
        $name = $arguments["job"] ?? fatal_error("job is required");
        $job = $this->registry->job($name) ?? fatal_error("Unknown job \"$name\". Available: {$this->registry->names->join(", ")}.");
        $this->context->transactionAuthor = $this->user?->username ?? "system";
        $job->run($this->context);
        if ($this->context->hasChanges) {
            $this->context->save();
        }
        return $this->jsonResult(["status" => "completed", "job" => $name]);
    }
}
