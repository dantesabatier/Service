<?php

namespace Sabatier\Service;

/** @var string Environment key used to load the private signing key for JWT generation */
const JWTPrivateKey = "JWT_PRIVATE_KEY";
/** @var string Environment key defining the default validity duration (in seconds) for issued JWTs */
const JWTValidityTimeIntervalKey = "JWT_VALIDITY_TIME_INTERVAL";
/** @var string Environment key specifying the algorithm used to sign JWTs (e.g., RS256, HS512) */
const JWTSignatureAlgorithmKey = "JWT_SIGNATURE_ALGORITHM";
/** @var string Header claim key representing the signing algorithm used to secure the token (RFC 7519 §5.1) */
const JWTAlgorithmKey = "alg";
/** @var string Header claim key defining the token type; for JWT this is typically "JWT" (RFC 7519 §5.1) */
const JWTTypeKey = "typ";
/** @var string Payload claim key identifying the principal that issued the JWT */
const JWTIssuerKey = "iss";
/** @var string Payload claim key identifying the subject of the JWT (the user or entity) */
const JWTSubjectKey = "sub";
/** @var string Payload claim key identifying the intended recipients of the JWT */
const JWTAudienceKey = "aud";
/** @var string Payload claim key defining the expiration time after which the JWT must not be accepted */
const JWTExpirationTimeKey = "exp";
/** @var string Payload claim key indicating the time before which the JWT must not be accepted */
const JWTNotBeforeTimeKey = "nbf";
/** @var string Payload claim key indicating the time at which the JWT was issued */
const JWTIssuedAtTimeKey = "iat";
/** @var string Payload claim key providing a unique identifier for the JWT to prevent replay */
const JWTIdKey = "jti";
/** @var string Payload claim key listing the scopes granted to the token (space or array format depending on implementation) */
const JWTScopesKey = "scp";
/** @var string Payload claim key listing the authorization scopes for server-side permission checks */
const JWTAuthorizationScopesKey = "authz";
/** @var string Payload claim key providing the token version for the JWT */
const JWTVersionKey = "ver";
/** @var string Payload claim key indicating whether the authenticated entity is enabled and allowed to authenticate */
const JWTEnabledKey = "enb";
/** @var string Header value defining the canonical type of the JSON Web Token */
const JWTTypeValue = "JWT";
/** @var string Delimiter used to separate the JWT components (header, payload, signature) */
const JWTComponentDelimiter = ".";
/** @var int Expected number of components in a compact serialized JWT */
const JWTComponentCount = 3;
/** @var int Default validity duration (in seconds) used when no explicit value is provided */
const JWTValidityDefaultTimeInterval = 1800;
