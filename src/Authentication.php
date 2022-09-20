<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ObjectClass;

use function Sabatier\Foundation\substring_to_index;

/**
 * Class Authentication
 * @package Sabatier\Service
 */
class Authentication extends ObjectClass
{
    public readonly AuthenticationScheme $scheme;
    public readonly AuthenticationFactor $factor;

    public function __construct(public readonly Service $service)
    {
        $this->scheme = (($string = $this->service->request->valueForHttpHeaderField('Authorization')) && ($index = strpos($string, ' ')) && ($authenticationSchemeName = substring_to_index($string, $index))) ? AuthenticationScheme::tryFrom($authenticationSchemeName) ?? AuthenticationScheme::basic : AuthenticationScheme::basic;
        $this->factor = AuthenticationFactor::single;
    }
}
