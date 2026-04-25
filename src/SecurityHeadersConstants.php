<?php

namespace Sabatier\Service;

/** @var string Environment variable key for the `Content-Security-Policy` header value. */
const SecurityContentSecurityPolicyKey = "SECURITY_CSP";
/** @var string Environment variable key for the `Strict-Transport-Security` header value. */
const SecurityStrictTransportSecurityKey = "SECURITY_HSTS";
/** @var string Environment variable key for the `X-Content-Type-Options` header value. */
const SecurityXContentTypeOptionsKey = "SECURITY_X_CONTENT_TYPE_OPTIONS";
/** @var string Environment variable key for the `X-Frame-Options` header value. */
const SecurityXFrameOptionsKey = "SECURITY_X_FRAME_OPTIONS";
/** @var string Environment variable key for the `Referrer-Policy` header value. */
const SecurityReferrerPolicyKey = "SECURITY_REFERRER_POLICY";
/** @var string Environment variable key for the `Permissions-Policy` header value. */
const SecurityPermissionsPolicyKey = "SECURITY_PERMISSIONS_POLICY";
/** @var string Default `X-Content-Type-Options` value. Prevents MIME-type sniffing. */
const SecurityXContentTypeOptionsDefault = "nosniff";
/** @var string Default `X-Frame-Options` value. Restricts framing to the same origin. */
const SecurityXFrameOptionsDefault = "SAMEORIGIN";
/** @var string Default `Referrer-Policy` value. Sends the full URL only to same-origin requests. */
const SecurityReferrerPolicyDefault = "strict-origin-when-cross-origin";
/** @var string Default `Permissions-Policy` value. Disables access to camera, microphone, and geolocation. */
const SecurityPermissionsPolicyDefault = "camera=(), microphone=(), geolocation=()";
