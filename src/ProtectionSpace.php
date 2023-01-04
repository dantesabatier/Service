<?php

namespace Sabatier\Service;

abstract class ProtectionSpace extends Responder
{
    final public const authenticationMethodBasic = "Basic";
    final public const authenticationMethodBearer = "Bearer";
    public string $authenticationMethod = self::authenticationMethodBasic;
    public string $defaultAuthenticationMethod = self::authenticationMethodBasic;
    public ?string $username = null;
}
