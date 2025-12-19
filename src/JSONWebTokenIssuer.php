<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\read_random;

/** @internal */
final readonly class JSONWebTokenIssuer implements TokenIssuer
{
    public function __construct(private JSONWebTokenService $service, private Dictionary $environment)
    {
    }

    /**
     * @throws Exception
     */
    public function issue(Authorizable $subject, AuthenticationContext $context): string
    {
        $date = new Date();
        return $this->service->encode([JWTIssuerKey => $context->tokenIssuer, JWTSubjectKey => $subject->username, JWTExpirationTimeKey => $date->addingTimeInterval($this->environment[JWTValidityTimeIntervalKey] ?? 600)->timeIntervalSinceReferenceDate, JWTNotBeforeTimeKey => $date->timeIntervalSinceReferenceDate, JWTIssuedAtTimeKey => $date->timeIntervalSinceReferenceDate, JWTIdKey => base64_encode(read_random(16))]);
    }
}
