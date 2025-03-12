<?php

namespace Sabatier\Service;

enum AuthenticationScheme: string
{
    case basic = "Basic";
    case bearer = "Bearer";
    case digest = "Digest";
    case apiKey = "ApiKey";
}
