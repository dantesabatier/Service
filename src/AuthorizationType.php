<?php

namespace Sabatier\Service;

enum AuthorizationType: int
{
    case read = 0;
    case create = 1;
    case update = 2;
    case delete = 3;
}
