<?php

namespace Sabatier\Service;

/** @var string Key used to expose the authenticated user object in authentication responses */
const AuthenticationUserKey = "user";
/** @var string Key used to expose the issued authentication token in authentication responses */
const AuthenticationTokenKey = "token";
/** @var string Token scope value representing an access token */
const AuthenticationScopeAccess = "access";
/** @var string Token scope value representing a refresh token */
const AuthenticationScopeRefresh = "refresh";
/** @var string Key used to identify the refresh-token selector for rotation or lookup */
const AuthenticationRefreshSelector = "refresh";
/** @var string Key used to track the refresh token version for rotation or invalidation */
const AuthenticationRefreshTokenVersionKey = "refreshTokenVersion";
/** @var string Session key that indicates whether the current session is authenticated */
const SessionAuthenticatedKey = "authenticated";
/** @var string Session key storing the authenticated user's identifier */
const SessionUserKey = "user";
/** @var string Key used to store the authenticated user's username */
const AuthenticationUsernameKey = "username";
/** @var string Key used to store the authenticated user's password */
const AuthenticationPasswordKey = "password";
