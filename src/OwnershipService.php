<?php

declare(strict_types=1);

namespace Sabatier\Service;

/** @internal */
final readonly class OwnershipService
{
    public bool $isOwner;

    public function __construct(private OwnerResolver $resolver, private Authorizable $user)
    {
        $owner = $this->resolver->owner;
        $this->isOwner = $owner === null || $this->user->isEqual($owner);
    }
}
