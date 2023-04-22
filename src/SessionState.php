<?php

namespace Sabatier\Service;

enum SessionState: int
{
    case disabled = 0;
    case none = 1;
    case active = 2;
}
