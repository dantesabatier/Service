<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * The HTTP authentication schemes supported by the framework.
 *
 * The active scheme is determined by the `Authorization` request header and resolved
 * by the authentication providers registered with `AuthenticationManager`. Each case
 * maps to the scheme string as it appears in the `WWW-Authenticate` and `Authorization`
 * HTTP headers.
 *
 * - `basic` — Base64-encoded `username:password` credentials (RFC 7617). Suitable only over TLS.
 * - `bearer` — Opaque or structured token (RFC 6750), typically a JWT issued by `TokenIssuer`.
 * - `digest` — Challenge-response scheme (RFC 7616). More secure than Basic without TLS.
 * - `negotiate` — SPNEGO/Kerberos-based scheme for enterprise environments.
 * - `aws` — AWS Signature Version 4 HMAC-SHA256 scheme for service-to-service calls.
 */
enum AuthenticationScheme: string
{
    case basic = "Basic";
    case bearer = "Bearer";
    case digest = "Digest";
    case negotiate = "Negotiate";
    case aws = "AWS4-HMAC-SHA256";
}
