<?php

namespace Sabatier\Service;

/**
 * Enum AuthenticationScheme
 * @package Sabatier\Service
 */
enum AuthenticationScheme: string
{
    case basic = 'Basic';
    case bearer = 'Bearer';
    case digest = 'Digest';
    case hoba = 'HOBA';
    case mutual = 'Mutual';
    case negotiate = 'Negotiate';
    case open = 'OAuth';
    case scramSha1 = 'SCRAM-SHA-1';
    case scramSha256 = 'SCRAM-SHA-256';
    case vapid = 'vapid';
}
