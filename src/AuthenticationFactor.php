<?php

namespace Sabatier\Service;

/**
 * Enum AuthenticationFactor
 * @package Sabatier\Service
 */
enum AuthenticationFactor: int
{
    case single = 0;
    case double = 1;
    case multi = 2;
    case biometric = 3;
    case captcha = 4;
}
