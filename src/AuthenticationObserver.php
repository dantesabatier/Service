<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * Contract for an `Authenticatable` entity that wants to be told when it signs in.
 *
 * Implementing this interface is entirely optional: `AuthenticationManager` reports the sign-in
 * only to a user entity that declares it, so an application that keeps no record implements
 * nothing. It is deliberately separate from `Authenticatable` for that reason, and because a
 * sign-in is an HTTP event — the contract takes the request, which the minimal identity contract
 * has no business knowing about.
 *
 * @see Authenticatable
 * @see AuthenticationManager
 */
interface AuthenticationObserver
{
    /**
     * Notifies the entity that it has just been authenticated, after the identity is established.
     *
     * Implement it to record the sign-in — a `lastLoginDate` on the entity itself, or a row in a
     * history of its own design. The framework only reports the event; it neither reads what is
     * written nor requires that anything be.
     *
     * The whole request is passed rather than the individual facts about it, so that an entity can
     * draw on whatever it wants to keep — `$request->remoteAddress` for the peer address, or a
     * header such as `User-Agent` through `valueForHttpHeaderField()` — without this contract
     * having to name each one in advance.
     *
     * The implementation owns whatever transaction it needs: nothing in the sign-in path saves the
     * context, so an implementation that writes must commit its own changes.
     *
     * @param Request $request The request that established the identity.
     */
    public function didLogin(Request $request): void;
}
