<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;

/** @internal */
interface TokenIssuer
{
    public function issue(Authorizable $subject, AuthenticationContext $context, ArrayClass $technicalScopes): string;
}
