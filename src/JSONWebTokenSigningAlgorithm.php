<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * Signing algorithms for JSON Web Tokens (JWTs).
 */
enum JSONWebTokenSigningAlgorithm: string
{
    case hs256 = "HS256";
    case hs384 = "HS384";
    case hs512 = "HS512";
    case rs256 = "RS256";
    case rs384 = "RS384";
    case rs512 = "RS512";
    case es256 = "ES256";
    case es384 = "ES384";
    case es512 = "ES512";
    case ps256 = "PS256";
    case ps384 = "PS384";
    case ps512 = "PS512";
    case none = "none";
}
