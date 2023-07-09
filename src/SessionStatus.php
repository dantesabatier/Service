<?php

namespace Sabatier\Service;

enum SessionStatus: int
{
    case disabled = 0;
    case none = 1;
    case active = 2;
}
