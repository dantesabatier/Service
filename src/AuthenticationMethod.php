<?php

namespace Sabatier\Service;

enum AuthenticationMethod: string
{
    case basic = "Basic";
    case bearer = "Bearer";
    case digest = "Digest";
}
