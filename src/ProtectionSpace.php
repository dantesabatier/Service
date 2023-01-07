<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPRequestMethod;

use function Sabatier\Foundation\substring_to_index;

abstract class ProtectionSpace extends Responder
{
    final public const authenticationMethodBasic = "Basic";
    final public const authenticationMethodBearer = "Bearer";
    public readonly ?string $authenticationMethod;
    public string $defaultAuthenticationMethod = self::authenticationMethodBasic;
    public ?string $username = null;

    public function __construct()
    {
        parent::__construct();
        $this->authenticationMethod = $this->request->httpMethod != HTTPRequestMethod::options && ($authentication = $this->request->valueForHttpHeaderField("Authorization")) && ($authenticationIndex = (int)strpos($authentication, " ")) ? substring_to_index($authentication, $authenticationIndex) : null;
    }
}
