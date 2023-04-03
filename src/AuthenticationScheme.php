<?php

namespace Sabatier\Service;

enum AuthenticationScheme: string
{
    case basic = "Basic";
    case digest = "Digest";
    case bearer = "Bearer";
}
