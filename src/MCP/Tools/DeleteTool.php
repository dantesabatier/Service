<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\NotFoundException;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

/** @internal */
final class DeleteTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "delete";
    }
    #[Override]
    public bool $isOpenWorld {
        get => false;
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => ["entity" => ["type" => "string", "description" => "Always required. Entity name from the data model — call describe_model first if unsure."], "objectID" => ["type" => "integer"]],
            "required" => ["entity", "objectID"],
        ];
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function authorizationRequirements(Dictionary $arguments): ?AuthorizationRequirements
    {
        /** @var string $entity */
        $entity = $arguments["entity"] ?? fatal_error("entity is required");
        return AuthorizationRequirements::one($entity, AuthorizationType::delete);
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        /** @var string $entity */
        $entity = $arguments["entity"] ?? fatal_error("entity is required");
        $objectID = $arguments["objectID"] ?? fatal_error("objectID is required");
        $request = $this->fetchRequest($entity);
        $request->predicate = $this->buildPredicate("%K = %d", new ArrayClass([ManagedObjectObjectIDKey, $objectID]));
        $this->applySecurityScope($request);
        /** @var ManagedObject $object */
        $object = $this->context->fetch($request)->first ?? throw new NotFoundException();
        $this->enforceOwnership($object);
        $this->enforceResourceAccess($object);
        $this->context->delete($object);
        $this->context->save();
        return $this->jsonResult(["deleted" => $objectID]);
    }
}
