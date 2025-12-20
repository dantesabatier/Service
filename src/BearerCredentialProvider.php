<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\Networking\URLCredential;

/** @internal */
final readonly class BearerCredentialProvider
{
    public function __construct(private JSONWebTokenService $service)
    {
    }

    /**
     * @throws Exception
     */
    public function decode(AuthenticationContext $context): ?URLCredential
    {
        $token = $this->service->decode($context->authorizationHeader->value);
        if (!($username = $token->payload->sub)) {
            return null;
        }
        return new URLCredential($username);
    }
}
