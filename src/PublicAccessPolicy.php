<?php

namespace Sabatier\Service;

use Override;

final class PublicAccessPolicy extends AccessPolicy
{
    #[Override]
    public function shouldCheck(Request $request): bool
    {
        return false;
    }
}
