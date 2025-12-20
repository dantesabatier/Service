<?php

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Date;
use function Sabatier\Foundation\read_random;

/** @internal */
final readonly class JSONWebTokenIssuer implements TokenIssuer
{
    public function __construct(private JSONWebTokenService $service, private AuthorizationScopeBuilder $scopeBuilder, private ManagedObjectContext $managedObjectContext, private int $validityTimeInterval = 1800)
    {
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function issue(Authorizable $subject, AuthenticationContext $context): string
    {
        $date = new Date();
        return $this->service->encode([JWTIssuerKey => $context->tokenIssuer, JWTSubjectKey => $subject->username, JWTExpirationTimeKey => $date->addingTimeInterval($this->validityTimeInterval)->timeIntervalSinceReferenceDate, JWTNotBeforeTimeKey => $date->timeIntervalSinceReferenceDate, JWTIssuedAtTimeKey => $date->timeIntervalSinceReferenceDate, JWTIdKey => base64_encode(read_random(16)), JWTScopesKey => $this->scopeBuilder->build($subject, $this->managedObjectContext)->array]);
    }
}
