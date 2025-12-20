<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\ArrayClass;

class SessionIdentitySource extends IdentitySource
{
    private(set) ArrayClass $scopes {
        get => $this->scopes ??= new ArrayClass($this->session->valueForKey("scopes") ?? []);
    }
    private readonly Session $session;

    public function __construct(?Authorizable $subject, Session $session)
    {
        parent::__construct($subject);
        $this->session = $session;
    }

    #[Override]
    public function invalidate(): void
    {
        $this->session->invalidate();
    }
}
