<?php

namespace Sabatier\Service;

use Override;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;

/** @internal */
final readonly class JWTScopeEvaluator implements AccessEvaluator
{
    public function __construct(private string $requiredScope)
    {
    }

    #[Override]
    public function evaluate(Request $request, Authentication $authentication, Session $session, Dictionary $environment, AuthorizationService $authorizationService, ManagedObjectContext $managedObjectContext): bool
    {
        return $authentication->technicalScopes->isEmpty || $authentication->technicalScopes->containsElement($this->requiredScope);
    }
}
