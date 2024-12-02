<?php

namespace Sabatier\Service;

enum AuthorizationType: int
{
    case read = 0;
    case write = 1;
    case delete = 2;
}
