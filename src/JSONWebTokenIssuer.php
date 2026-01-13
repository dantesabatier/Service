<?php

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use function Sabatier\Foundation\read_random;

/** @internal */
final readonly class JSONWebTokenIssuer implements TokenIssuer
{
    public function __construct(private JSONWebTokenService $service, private AuthorizationScopeBuilder $scopeBuilder, private ManagedObjectContext $managedObjectContext, private float $validityTimeInterval = JWTValidityDefaultTimeInterval)
    {
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function issue(Authorizable $subject, AuthenticationContext $context, ArrayClass $technicalScopes): string
    {
        $date = new Date();
        return $this->service->encode([JWTIssuerKey => $context->tokenIssuer, JWTSubjectKey => $subject->username, JWTEnabledKey => $subject->isEnabled, JWTExpirationTimeKey => $date->addingTimeInterval($this->validityTimeInterval)->timeIntervalSinceReferenceDate, JWTNotBeforeTimeKey => $date->timeIntervalSinceReferenceDate, JWTIssuedAtTimeKey => $date->timeIntervalSinceReferenceDate, JWTIdKey => base64_encode(read_random(16)), JWTVersionKey => $subject->version, JWTScopesKey => $technicalScopes->array, JWTAuthorizationScopesKey => $this->scopeBuilder->build($subject, $this->managedObjectContext)->array]);
    }
}
