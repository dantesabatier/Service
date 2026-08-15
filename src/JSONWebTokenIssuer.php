<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use function Sabatier\Foundation\read_random;

/**
 * Issues signed JSON Web Tokens for an authenticated identity.
 *
 * The token embeds the subject's username, enabled flag and refresh token version, the
 * requested technical scopes, and the authorization scopes resolved from the subject's roles
 * by {@see AuthorizationScopeBuilder}. Validity is bounded by `$validityTimeInterval`, which
 * defaults to {@see JWTValidityDefaultTimeInterval}.
 *
 * An audience may be named to bind the token to a single resource, so that it opens that one
 * and no other. The claim is omitted when none is given, which is how the tokens minted for
 * the application itself stay usable everywhere.
 *
 * All dependencies are supplied through the constructor, so issuance does not require an
 * incoming HTTP request and can be driven directly from a CLI job or scheduled task.
 *
 * @see TokenIssuer
 * @see JSONWebTokenService
 * @see AuthorizationScopeBuilder
 */
final readonly class JSONWebTokenIssuer implements TokenIssuer
{
    /**
     * Initializes a new instance of the JSONWebTokenIssuer class.
     *
     * @param JSONWebTokenService $service The service that encodes and signs the token.
     * @param AuthorizationScopeBuilder $scopeBuilder The builder that resolves authorization scopes from the subject's roles.
     * @param ManagedObjectContext $managedObjectContext The context used to resolve the subject's authorization scopes.
     * @param float $validityTimeInterval The lifetime of the issued token, in seconds.
     */
    public function __construct(private JSONWebTokenService $service, private AuthorizationScopeBuilder $scopeBuilder, private ManagedObjectContext $managedObjectContext, private float $validityTimeInterval = JWTValidityDefaultTimeInterval)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    #[Override]
    public function issue(Authorizable $subject, ArrayClass $technicalScopes, string $audience = ""): string
    {
        $date = new Date();
        $claims = [JWTIssuerKey => $this->service->issuer, JWTSubjectKey => $subject->username, JWTEnabledKey => $subject->isEnabled, JWTExpirationTimeKey => $date->addingTimeInterval($this->validityTimeInterval)->timeIntervalSinceReferenceDate, JWTNotBeforeTimeKey => $date->timeIntervalSinceReferenceDate, JWTIssuedAtTimeKey => $date->timeIntervalSinceReferenceDate, JWTIdKey => base64_encode(read_random(16)), JWTVersionKey => $subject->refreshTokenVersion, JWTScopesKey => $technicalScopes->array, JWTAuthorizationScopesKey => $this->scopeBuilder->build($subject, $this->managedObjectContext)->array];
        if ($audience !== "") {
            $claims[JWTAudienceKey] = $audience;
        }
        return $this->service->encode($claims);
    }
}
