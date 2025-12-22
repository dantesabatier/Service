<?php

namespace Sabatier\Service;

/** @var string Key used to expose the authenticated user in authentication responses */
const AuthenticationUserKey = "user";

/** @var string Key used to expose the issued token in authentication responses */
const AuthenticationTokenKey = "token";
const AuthenticationScopeAccess = "access";
const AuthenticationScopeRefresh = "refresh";

/** @var string Indicates whether the current session is authenticated */
const SessionAuthenticatedKey = "authenticated";

/** @var string Stores the authenticated user identifier in the session */
const SessionUserKey = "user";
