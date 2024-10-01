<?php

namespace Sabatier\Service;

const JWTPrivateKeyPreferenceKey = "JWTPrivateKeyPreferenceKey";
const JWTValidityTimeIntervalPreferenceKey = "JWTValidityTimeIntervalPreferenceKey";
/** @var string Token type. */
const JWTTypeHeaderKey = "typ";
/** @var string Content type. */
const JWTContentTypeHeaderKey = "cty";
/** @var string Algorithm. */
const JWTAlgorithmHeaderKey = "alg";
/** @var string Issuer. */
const JWTIssuerField = "iss";
/** @var string Subject. */
const JWTSubjectField = "sub";
/** @var string Audience. */
const JWTAudienceField = "aud";
/** @var string Expiration Time. */
const JWTExpirationField = "exp";
/** @var string Not Before. */
const JWTNotBeforeField = "nbf";
/** @var string Issued at. */
const JWTIssuedField = "iat";
/** @var string JWT ID, Case-sensitive unique identifier of the token even among different issuers. */
const JWTUniqueIDField = "jti";
const JWTDataField = "dat";
