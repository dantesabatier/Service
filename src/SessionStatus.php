<?php

namespace Sabatier\Service;

enum SessionStatus: int
{
    case disabled = PHP_SESSION_DISABLED;
    case none = PHP_SESSION_NONE;
    case active = PHP_SESSION_ACTIVE;
}
