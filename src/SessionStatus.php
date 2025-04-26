<?php

namespace Sabatier\Service;

/**
 * Represents the status of a session.
 */
enum SessionStatus: int
{
    case disabled = 0;
    case none = 1;
    case active = 2;
}
