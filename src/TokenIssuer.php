<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;

/**
 * Contract for services that issue bearer tokens for an authenticated identity.
 *
 * Issuance is decoupled from authentication: a `TokenIssuer` receives a subject that has
 * already been resolved and validated, and mints a token for it. It carries no dependency
 * on the incoming HTTP request, so a token can be issued outside of a request — for example
 * from a CLI job or a scheduled task.
 *
 * `JSONWebTokenIssuer` is the built-in implementation, backing the `/login` and `/refresh`
 * actions of `AuthenticationManager`.
 *
 * @see JSONWebTokenIssuer
 * @see Authorizable
 * @see AuthenticationManager
 */
interface TokenIssuer
{
    /**
     * Issues a token for the given already-authenticated subject.
     *
     * @param Authorizable $subject The authenticated identity the token is issued for.
     * @param ArrayClass<string> $technicalScopes The technical scopes granted to the token (e.g. access and refresh).
     * @param string $audience The resource the token is meant for, or an empty string to leave the claim out. A token that names its audience is accepted only by the resource that expects that name, so a credential minted for one endpoint does not open another.
     * @return string The encoded token.
     */
    public function issue(Authorizable $subject, ArrayClass $technicalScopes, string $audience = ""): string;
}
