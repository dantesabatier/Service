<?php

namespace Sabatier\Service;

enum AuthenticationScheme: string
{
    case basic = "Basic";
    case digest = "Digest";
    case hoba = "HOBA";
    case mutual = "Mutual";
    case negotiate = "Negotiate";
    case vapid = "VAPID";
    case scram = "SCRAM";
    case aws = "AWS4-HMAC-SHA256";
    case bearer = "Bearer";
}
