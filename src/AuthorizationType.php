<?php

namespace Sabatier\Service;

/**
 * Represents types of authorization for various actions.
 */
enum AuthorizationType: int
{
    case read = 0;
    case create = 1;
    case update = 2;
    case delete = 3;
}
