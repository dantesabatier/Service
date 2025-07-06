<?php

namespace Sabatier\Service;

/**
 * Represents an exception specific to JSON Web Token (JWT) handling.
 *
 * Typically, thrown when an error related to JWT creation, decoding, or validation occurs.
 */
class JSONWebTokenException extends UnauthorizedException
{
}
