<?php

namespace Sabatier\Service;

/**
 * Defines various authentication schemes that can be used for securing communications.
 */
enum AuthenticationScheme: string
{
    case basic = "Basic";
    case bearer = "Bearer";
    case digest = "Digest";
    case negotiate = "Negotiate";
    case oauth = "OAuth";
    case aws = "AWS4-HMAC-SHA256";
}
