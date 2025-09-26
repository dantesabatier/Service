<?php

namespace Sabatier\Service;

use Override;

final class DefaultAccessPolicy extends AccessPolicy
{
    #[Override]
    public function shouldCheck(Request $request): bool
    {
        return false;
    }
}
