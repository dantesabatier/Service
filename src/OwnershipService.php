<?php

namespace Sabatier\Service;

/** @internal */
final readonly class OwnershipService
{
    public bool $isOwner;

    public function __construct(private OwnerResolver $resolver, private Authorizable $user)
    {
        $this->isOwner = $this->resolver->owner?->username === $this->user->username;
    }
}
