<?php

namespace Sabatier\Service;

/**
 * An abstract class that defines the strategy for encoding and decoding JSON Web Tokens (JWTs).
 */
abstract class JSONWebTokenCoderStrategy
{
    public static JSONWebTokenSigningAlgorithm $algorithm = JSONWebTokenSigningAlgorithm::none;
}
