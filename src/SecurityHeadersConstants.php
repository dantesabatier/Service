<?php

namespace Sabatier\Service;

const SecurityContentSecurityPolicyKey = "SECURITY_CSP";
const SecurityStrictTransportSecurityKey = "SECURITY_HSTS";
const SecurityXContentTypeOptionsKey = "SECURITY_X_CONTENT_TYPE_OPTIONS";
const SecurityXFrameOptionsKey = "SECURITY_X_FRAME_OPTIONS";
const SecurityReferrerPolicyKey = "SECURITY_REFERRER_POLICY";
const SecurityPermissionsPolicyKey = "SECURITY_PERMISSIONS_POLICY";
const SecurityXContentTypeOptionsDefault = "nosniff";
const SecurityXFrameOptionsDefault = "SAMEORIGIN";
const SecurityReferrerPolicyDefault = "strict-origin-when-cross-origin";
const SecurityPermissionsPolicyDefault = "camera=(), microphone=(), geolocation=()";
