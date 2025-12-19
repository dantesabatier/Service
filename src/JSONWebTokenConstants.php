<?php

namespace Sabatier\Service;

const JWTPrivateKey = "JWT_PRIVATE_KEY";
const JWTValidityTimeIntervalKey = "JWT_VALIDITY_TIME_INTERVAL";
const JWTSignatureAlgorithmKey = "JWT_SIGNATURE_ALGORITHM";
const JWTAlgorithmKey = "alg";
const JWTTypeKey = "typ";
const JWTIssuerKey = "iss";
const JWTSubjectKey = "sub";
const JWTAudienceKey = "aud";
const JWTExpirationTimeKey = "exp";
const JWTNotBeforeTimeKey = "nbf";
const JWTIssuedAtTimeKey = "iat";
const JWTIdKey = "jti";
const JWTUsernameKey = "username";
const JWTTypeValue = "JWT";
const JWTComponentDelimiter = ".";
const JWTComponentCount = 3;
