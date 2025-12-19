<?php

namespace Sabatier\Service;

/** @internal */
interface TokenIssuer
{
    public function issue(Authorizable $subject, AuthenticationContext $context): string;
}
