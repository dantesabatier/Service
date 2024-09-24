<?php

namespace Sabatier\Service;

const JWTPrivateKey = "JWTPrivateKey";
const JWTValidityKey = "JWTValidity";
/** @var string Token type, If present, it must be set to a registered IANA Media Type. */
const JWTTypeHeaderKey = "typ";
/** @var string Content type, If nested signing or encryption is employed, it is recommended to set this to JWT; otherwise, omit this field. */
const JWTContentTypeHeaderKey = "cty";
/** @var string Message authentication code algorithm, The issuer can freely set an algorithm to verify the signature on the token. However, some supported algorithms are insecure. */
const JWTAlgorithmHeaderKey = "alg";
/** @var string Issuer, Identifies principal that issued the JWT. */
const JWTIssuerField = "iss";
/** @var string Subject, Identifies the subject of the JWT. */
const JWTSubjectField = "sub";
/** @var string Audience, Identifies the recipients that the JWT is intended for. Each principal intended to process the JWT must identify itself with a value in the audience claim. If the principal processing the claim does not identify itself with a value in the aud claim when this claim is present, then the JWT must be rejected. */
const JWTAudienceField = "aud";
/** @var string Expiration Time, Identifies the expiration time on and after which the JWT must not be accepted for processing. */
const JWTExpirationField = "exp";
/** @var string Not Before, Identifies the time on which the JWT will start to be accepted for processing. */
const JWTNotBeforeField = "nbf";
/** @var string Issued at, Identifies the time at which the JWT was issued. */
const JWTIssuedField = "iat";
/** @var string JWT ID, Case-sensitive unique identifier of the token even among different issuers. */
const JWTUniqueIDField = "jti";
